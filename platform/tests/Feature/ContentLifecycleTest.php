<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Media,SourceRecord,ImportRun,Role,User};
use App\Services\Access;
use App\Services\Importing\{ContentMetadataImporter,LocalMediaLinks,ImportProgress};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Storage};

class ContentLifecycleTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();Queue::fake();Storage::fake('local');app(Access::class)->seed();$user=User::factory()->create();$user->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($user);}
    private function record(string $id='abcdefghijk',string $kind='video',array $metadata=[]): SourceRecord
    {return SourceRecord::create(['source'=>'youtube','source_id'=>$id,'kind'=>$kind,'title'=>'Accepted title','body'=>'Original body','status'=>'ready','metadata'=>$metadata]);}
    private function file(string $name,string $kind='video'): Media
    {Storage::disk('local')->put($name,$name);return Media::create(['source'=>'youtube','source_id'=>'abcdefghijk:'.$name,'title'=>$name,'original_name'=>$name,'disk'=>'local','path'=>$name,'kind'=>$kind,'mime'=>$kind==='image'?'image/png':'video/mp4','bytes'=>strlen($name),'status'=>'ready','metadata'=>['youtube_id'=>'abcdefghijk']]);}
    public function test_content_trash_restores_its_children_without_deleting_originals_or_resurrecting_previous_trash(): void
    {
        $file=$this->file('old.mp4');$record=$this->record(metadata:['media_ids'=>[$file->id]]);
        $comment=$this->record('comment1','comment',['parent_source_id'=>$record->source_id]);
        $previous=$this->record('comment2','comment',['parent_source_id'=>$record->source_id]);$previous->delete();
        $this->deleteJson('/desktop/content/'.$record->id,['confirmation'=>'DELETE'])->assertOk();
        $this->assertSoftDeleted($record);$this->assertSoftDeleted($comment);$this->assertTrue(Storage::disk('local')->exists('old.mp4'));
        $this->getJson('/desktop/content?section=videos')->assertJsonCount(0,'data');
        $this->getJson('/desktop/content?section=videos&trash=deleted')->assertJsonPath('meta.total',1);
        $this->getJson('/desktop/content/'.$record->id)->assertJsonPath('trashed',true);
        $this->postJson('/desktop/content/'.$record->id.'/restore')->assertOk();
        $this->assertFalse($record->fresh()->trashed());$this->assertFalse($comment->fresh()->trashed());$this->assertSoftDeleted($previous);
    }
    public function test_reimport_does_not_restore_a_deleted_record(): void
    {
        $record=$this->record();$this->deleteJson('/desktop/content/'.$record->id,['confirmation'=>'DELETE'])->assertOk();
        $merged=app(ContentMetadataImporter::class)->record('youtube',$record->source_id,'video','New title','New import',[]);
        $this->assertTrue($merged->trashed());$this->assertSame('Accepted title',$merged->title);$this->assertDatabaseCount('source_records',1);
    }
    public function test_replacing_video_and_cover_is_scoped_and_old_links_stay_excluded_after_repair_and_import(): void
    {
        $old=$this->file('old.mp4');$new=$this->file('new.mp4');$image=$this->file('old.png','image');$cover=$this->file('new.png','image');
        // Avoid auto-discovering the replacement before it is explicitly chosen.
        foreach([$new,$cover]as $file)$file->update(['source'=>'upload','metadata'=>[]]);
        $record=$this->record(metadata:['media_ids'=>[$old->id,$image->id],'cover_media_id'=>$image->id]);
        $other=$this->record('other','video',['media_ids'=>[$old->id,$image->id]]);app(LocalMediaLinks::class)->repair($record);app(LocalMediaLinks::class)->repair($other);
        foreach([['role'=>'video','media_id'=>$new->id],['role'=>'cover','media_id'=>$cover->id]]as $data)
            $this->postJson('/desktop/content/'.$record->id.'/assets',['action'=>'replace',...$data])->assertOk();
        app(ContentMetadataImporter::class)->record('youtube',$record->source_id,'video','Import','Longer import text',['media_ids'=>[$old->id,$image->id]]);
        $ids=app(LocalMediaLinks::class)->ids($record->fresh());$this->assertContains($new->id,$ids);$this->assertContains($cover->id,$ids);$this->assertNotContains($old->id,$ids);$this->assertNotContains($image->id,$ids);
        $this->assertContains($old->id,app(LocalMediaLinks::class)->ids($other->fresh()));$this->assertSame('Original body',$record->fresh()->body);
        foreach([$old,$new,$image,$cover]as $file)$this->assertTrue(Storage::disk('local')->exists($file->path));
    }
    public function test_attachment_removal_and_validation_do_not_change_other_files(): void
    {
        $image=$this->file('image.png','image');$video=$this->file('video.mp4');$record=$this->record('post','post',['media_ids'=>[$image->id]]);
        $this->postJson('/desktop/content/'.$record->id.'/assets',['action'=>'replace','role'=>'cover','media_id'=>$video->id])->assertUnprocessable();
        $this->postJson('/desktop/content/'.$record->id.'/assets',['action'=>'detach','role'=>'attachment','old_media_id'=>$video->id])->assertUnprocessable();
        $this->postJson('/desktop/content/'.$record->id.'/assets',['action'=>'detach','role'=>'attachment','old_media_id'=>$image->id])->assertOk();
        $this->assertSame([],app(LocalMediaLinks::class)->ids($record->fresh()));$this->assertTrue(Storage::disk('local')->exists('image.png'));
    }
    public function test_reset_also_catches_ai_generated_mht_posts_but_preserves_manually_edited_content(): void
    {
        $file=$this->file('web.mht','document');$file->update(['classification_origin'=>'ai']);
        $bad=SourceRecord::create(['source'=>'intake','source_id'=>'media:'.$file->id,'kind'=>'post','title'=>'MHT AI caption','status'=>'ready','metadata'=>['media_ids'=>[$file->id]]]);
        $manual=SourceRecord::create(['source'=>'intake','source_id'=>'own-post','kind'=>'post','title'=>'Owner post','status'=>'ready','metadata'=>['classification_origin'=>'manual']]);
        $this->postJson('/desktop/imports/catalog-reset',['confirmation'=>'RESET'])->assertOk()->assertJsonPath('records',1);
        $this->assertSame('catalog-reset',$bad->fresh()->source);$this->assertSame('intake',$manual->fresh()->source);$this->assertTrue(Storage::disk('local')->exists('web.mht'));
    }
    public function test_file_removal_is_reversible_and_does_not_break_existing_content(): void
    {
        $file=$this->file('old.mp4');$record=$this->record(metadata:['media_ids'=>[$file->id]]);
        foreach([true,false]as $archived){$this->patchJson('/desktop/media/organize',['ids'=>[$file->id],'archived'=>$archived])->assertOk();$this->assertSame($archived,$file->fresh()->archived_at!==null);}
        $this->assertContains($file->id,app(LocalMediaLinks::class)->ids($record));$this->assertTrue(Storage::disk('local')->exists('old.mp4'));
    }
    public function test_active_summary_is_not_limited_to_history_page_and_checkpoint_updates_heartbeat(): void
    {
        $run=ImportRun::create(['source'=>'youtube-takeout','status'=>'running','progress'=>['stage'=>'extract'],'updated_at'=>now()->subHour()]);
        foreach(range(1,21)as $n)ImportRun::create(['source'=>'intake','status'=>'complete','updated_at'=>now()->addMinute()]);
        $this->getJson('/desktop/imports')->assertOk()->assertJsonPath('meta.active',1)->assertJsonPath('meta.active_run.id',$run->id);
        app(ImportProgress::class)->checkpoint($run);$this->assertTrue($run->fresh()->updated_at->greaterThan(now()->subMinute()));
    }
    public function test_mutations_require_permission_and_confirmation(): void
    {
        $record=$this->record();$this->deleteJson('/desktop/content/'.$record->id)->assertUnprocessable();
        $this->actingAs(User::factory()->create());$this->deleteJson('/desktop/content/'.$record->id,['confirmation'=>'DELETE'])->assertForbidden();
        $this->postJson('/desktop/content/'.$record->id.'/assets',['action'=>'detach','role'=>'attachment'])->assertForbidden();$this->assertFalse($record->fresh()->trashed());
    }
}
