<?php
namespace Tests\Feature;
use App\Models\Media;
use App\Models\MediaOriginal;
use App\Models\SourceRecord;
use App\Models\ImportRun;
use App\Models\ImportItem;
use App\Models\User;
use App\Models\Role;
use App\Services\Access;
use App\Services\Importing\CatalogReset;
use App\Services\Importing\ContentAssignment;
use App\Services\Importing\ImportedMediaRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
class CatalogPlaybackTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();app(Access::class)->seed();Storage::fake('local');$user=User::factory()->create();$user->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($user);}
    private function media(string $kind='video',string $name='fixture.mp4'): Media
    {
        Storage::disk('local')->put($name,'0123456789');return app(ImportedMediaRegistry::class)->register(['source'=>'upload','source_id'=>$name,'title'=>'Fixture','original_name'=>$name,'kind'=>$kind,'mime'=>$kind==='image'?'image/png':'video/mp4','bytes'=>10,'disk'=>'local','path'=>$name,'sha256'=>hash('sha256','0123456789'),'status'=>'unsorted','metadata'=>[]]);
    }
    public function test_desktop_exposes_takeout_catalog_reset_and_local_video_check(): void
    {
        $this->get('/desktop')->assertOk()->assertSee('data-local-video-check',false)->assertSee('data-takeout-batch',false)->assertSee('data-catalog-reset',false);
    }
    public function test_preview_supports_range_and_original_location_fallback(): void
    {
        $media=$this->media();$this->withHeader('Range','bytes=0-1')->get('/desktop/media/'.$media->id.'/preview')->assertStatus(206)->assertHeader('Content-Range','bytes 0-1/10');
        Storage::disk('local')->put('alternate.mp4','0123456789');app(ImportedMediaRegistry::class)->register(['source'=>'youtube','source_id'=>'alternate','title'=>'Alternate','original_name'=>'alternate.mp4','kind'=>'video','mime'=>'video/mp4','bytes'=>10,'disk'=>'local','path'=>'alternate.mp4','sha256'=>hash('sha256','0123456789'),'status'=>'unsorted']);
        Storage::disk('local')->delete('fixture.mp4');$this->withHeader('Range','bytes=2-3')->get('/desktop/media/'.$media->id.'/preview')->assertStatus(206)->assertHeader('Content-Range','bytes 2-3/10');$this->assertDatabaseCount('media',1);
    }
    public function test_image_classification_does_not_create_a_post(): void
    {
        $image=$this->media('image','picture.png');app(ContentAssignment::class)->media($image,['target_profile'=>'posts','title'=>'Image description','status'=>'ready'],'ai');
        $this->assertDatabaseCount('source_records',0);$this->assertSame('media_library',$image->fresh()->metadata['target_profile']);
    }
    public function test_manual_upload_reuses_server_original_and_finish_is_idempotent(): void
    {
        $original=app(ImportedMediaRegistry::class)->register(['source'=>'local-folder','source_id'=>'original','title'=>'Owner title','original_name'=>'original.txt','kind'=>'document','mime'=>'text/plain','bytes'=>10,'disk'=>'local','path'=>'server.txt','sha256'=>hash('sha256','0123456789'),'status'=>'ready']);Storage::disk('local')->put('server.txt','0123456789');
        $start=$this->postJson('/desktop/media/uploads',['request_key'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'new-name.txt','size'=>10])->assertOk()->json('id');
        $this->call('POST','/desktop/media/uploads/'.$start.'/chunk',[],[],[],['CONTENT_TYPE'=>'application/octet-stream','HTTP_X_UPLOAD_OFFSET'=>'0','HTTP_X_CHUNK_SHA256'=>hash('sha256','0123456789')],'0123456789')->assertOk();
        foreach([1,2] as $n)$this->postJson('/desktop/media/uploads/'.$start.'/finish')->assertOk()->assertJsonPath('media_id',$original->id);
        $this->assertDatabaseCount('media',1);$this->assertDatabaseCount('media_originals',2);$this->assertSame('Owner title',$original->fresh()->title);$this->assertTrue(Storage::disk('local')->exists('server.txt'));
    }
    public function test_catalog_reset_preserves_originals_own_records_and_is_reversible(): void
    {
        $image=$this->media('image','picture.png');$video=SourceRecord::create(['source'=>'youtube','source_id'=>'abcdefghijk','kind'=>'video','title'=>'Old video','body'=>'Old body','status'=>'ready','metadata'=>[]]);
        $bad=SourceRecord::create(['source'=>'upload','source_id'=>'media:'.$image->id,'kind'=>'post','title'=>'Image-derived post','status'=>'ready','metadata'=>['media_ids'=>[$image->id]]]);
        $own=SourceRecord::create(['source'=>'upload','source_id'=>'own-article','kind'=>'post','title'=>'Own article','status'=>'ready','metadata'=>[]]);
        $result=$this->postJson('/desktop/imports/catalog-reset',['confirmation'=>'RESET'])->assertOk()->json();
        $this->assertSame(2,$result['records']);$this->assertSame('catalog-reset',$bad->fresh()->source);$this->assertSame('upload',$own->fresh()->source);$this->assertTrue(Storage::disk('local')->exists('picture.png'));
        $this->getJson('/desktop/content?section=posts')->assertJsonPath('meta.total',1);
        $this->postJson('/desktop/imports/catalog-restore',['reset_id'=>$result['reset_id']])->assertOk();$this->assertSame('youtube',$video->fresh()->source);$this->assertSame('upload',$bad->fresh()->source);
    }
    public function test_restore_conflict_does_not_overwrite_new_takeout_records(): void
    {
        $old=SourceRecord::create(['source'=>'youtube','source_id'=>'abcdefghijk','kind'=>'video','title'=>'Old','status'=>'ready','metadata'=>[]]);$result=app(CatalogReset::class)->reset();
        $new=SourceRecord::create(['source'=>'youtube','source_id'=>'abcdefghijk','kind'=>'video','title'=>'New','status'=>'unsorted','metadata'=>[]]);
        $this->postJson('/desktop/imports/catalog-restore',['reset_id'=>$result['reset_id']])->assertStatus(409);$this->assertSame('New',$new->fresh()->title);$this->assertSame('catalog-reset',$old->fresh()->source);
    }
    public function test_audit_report_distinguishes_server_inventory_from_browser_confirmation(): void
    {
        Queue::fake();$media=$this->media();Storage::disk('local')->delete($media->path);
        $run=$this->postJson('/desktop/imports/video-check')->assertOk()->json('import_id');app(\App\Services\Importing\ImportCenter::class)->run(ImportRun::findOrFail($run));
        $report=$this->getJson('/desktop/imports/'.$run.'/report')->assertOk();$this->assertSame('missing',$report->json('data.0.outcome'));$this->assertSame('not_checked',$report->json('data.0.metadata.browser_status'));
        $id=$report->json('data.0.id');$this->postJson('/desktop/imports/'.$run.'/items/'.$id.'/browser',['status'=>'failed','reason'=>'Cannot play'])->assertOk();
        $this->assertSame('failed',ImportItem::findOrFail($id)->metadata['browser_status']);
        $other=ImportRun::create(['source'=>'local-video-check']);$this->postJson('/desktop/imports/'.$other->id.'/items/'.$id.'/browser',['status'=>'playable'])->assertNotFound();
    }
    public function test_post_children_are_grouped_under_the_original_post(): void
    {
        $post=SourceRecord::create(['source'=>'youtube','source_id'=>'UgOriginal','kind'=>'post','title'=>'Original','status'=>'unsorted','metadata'=>[]]);
        SourceRecord::create(['source'=>'youtube','source_id'=>'comment:UgOriginal:C1','kind'=>'comment','title'=>'Comment','body'=>'Real comment','status'=>'unsorted','metadata'=>['parent_source_id'=>'UgOriginal']]);
        $this->getJson('/desktop/content/'.$post->id.'/children')->assertOk()->assertJsonPath('meta.total',1)->assertJsonPath('data.0.body','Real comment');
    }
}
