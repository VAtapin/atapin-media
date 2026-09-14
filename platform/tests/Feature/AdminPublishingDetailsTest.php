<?php
namespace Tests\Feature;
use App\Models\{Media,SourceRecord,User,Role};
use App\Services\{Access,EditorialPlanning};
use App\Services\Publishing\{PlatformText,MediaResolver,ConnectionStore};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Queue,Http};
use Tests\TestCase;
class AdminPublishingDetailsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void{parent::setUp();app(Access::class)->seed();Queue::fake();Http::preventStrayRequests();$owner=User::factory()->create();$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($owner);Storage::fake('private');}
    private function record(array $metadata=[]):SourceRecord{return SourceRecord::create(['source'=>'manual','source_id'=>(string)\Illuminate\Support\Str::uuid(),'kind'=>'video','title'=>'Title','body'=>'Body','status'=>'ready','metadata'=>['public_section'=>'videos','public_published'=>false,...$metadata]]);}
    private function media(string $kind='video',array $metadata=[]):Media{Storage::disk('private')->put($path=\Illuminate\Support\Str::uuid().'.bin','original');return Media::create(['title'=>'Original','kind'=>$kind,'mime'=>$kind==='image'?'image/png':'video/mp4','disk'=>'private','path'=>$path,'original_name'=>$path,'sha256'=>hash('sha256',$path),'bytes'=>800,'status'=>'ready','metadata'=>$metadata]);}
    public function test_original_video_facts_and_filters_respect_exclusions_and_trash():void
    {
        $media=$this->media('video',['technical'=>['duration'=>90.5]]);$record=$this->record(['media_ids'=>[$media->id]]);
        $this->getJson('/desktop/content?section=videos&duration_min=90&bytes_max=900&processing=ready')->assertOk()->assertJsonPath('meta.total',1)->assertJsonPath('data.0.video_duration',90.5)->assertJsonPath('data.0.video_bytes',800);
        $this->getJson('/desktop/content?section=videos&duration_max=80')->assertOk()->assertJsonPath('meta.total',0);
        $record->update(['metadata'=>[...$record->metadata,'excluded_media_ids'=>[$media->id]]]);$this->getJson('/desktop/content?section=videos&bytes_min=1')->assertJsonPath('meta.total',0);
        $record->update(['metadata'=>['public_section'=>'videos']]);$media->usages()->create(['subject_type'=>SourceRecord::class,'subject_id'=>(string)$record->id,'used_as'=>'video']);$this->getJson('/desktop/content?section=videos&bytes_min=1')->assertJsonPath('meta.total',1);
        $media->update(['archived_at'=>now()]);$this->getJson('/desktop/content?section=videos&bytes_min=1')->assertJsonPath('meta.total',0);
        $this->getJson('/desktop/content?section=videos&duration_min=-1')->assertUnprocessable();
    }
    public function test_platform_covers_hashtags_and_actual_preview_preserve_original():void
    {
        $image=$this->media('image');$record=$this->record();
        $this->patchJson('/desktop/content/'.$record->id,['title'=>'Title','body'=>'Body','kind'=>'video','status'=>'ready','platform_metadata'=>['youtube'=>['body'=>'Description','cover_media_id'=>$image->id,'hashtags'=>['Glaube','#Hoffnung']],'x'=>['body'=>str_repeat('Text ',100),'hashtags'=>['Glaube']]]])->assertOk();
        $record->refresh();$this->assertContains($image->id,$record->metadata['media_ids']);$this->assertSame($image->id,app(MediaResolver::class)->image($record,'youtube')['media']->id);
        $this->assertSame("Description\n\n#Glaube #Hoffnung",PlatformText::value($record,'youtube','body'));
        $this->getJson('/desktop/publishing/preview?record_id='.$record->id)->assertOk()->assertJsonPath('previews.youtube.cover.id',$image->id)->assertJsonPath('previews.youtube.caption',PlatformText::value($record,'youtube','body'))->assertJsonPath('previews.x.caption',PlatformText::xCaption($record))->assertJsonPath('previews.telegram.requires_website',true)->assertJsonMissing(['path'=>$image->path]);
        $this->assertFalse($record->metadata['public_published']);Storage::disk('private')->assertExists($image->path);
        $this->patchJson('/desktop/content/'.$record->id,['title'=>'Title','kind'=>'video','status'=>'ready','platform_metadata'=>['x'=>['hashtags'=>['invalid tag']]]])->assertUnprocessable();
    }
    public function test_independent_schedule_does_not_cancel_other_destination_and_blocks_claimed():void
    {
        $record=$this->record();$planning=app(EditorialPlanning::class);$owner=auth()->user();
        $connections=\Mockery::mock(ConnectionStore::class);$connections->shouldReceive('connected')->with('youtube')->andReturn(true);app()->instance(ConnectionStore::class,$connections);
        $both=$planning->schedule($record,$owner,['website','youtube'],now()->addDays(2)->toIso8601String());
        $site=$planning->schedule($record,$owner,['website'],now()->addDay()->toIso8601String());$this->assertSame(['youtube'],$both->fresh()->providers);$this->assertSame('scheduled',$both->fresh()->status);
        $site->update(['status'=>'queued']);$this->postJson('/desktop/planning',['record_id'=>$record->id,'providers'=>['website'],'publish_at'=>now()->addDays(3)->toIso8601String()])->assertConflict();$this->assertSame('queued',$site->fresh()->status);
    }
}
