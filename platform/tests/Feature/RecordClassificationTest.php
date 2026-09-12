<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Models\SourceRecord;
use App\Jobs\ClassifyImportedContent;
use App\Services\Importing\AiContentClassifier;
use App\Services\Importing\ContentAssignment;
class RecordClassificationTest extends TestCase
{
    use RefreshDatabase;
    private function owner(): void {Queue::fake();app(\App\Services\Access::class)->seed();$user=\App\Models\User::factory()->create();$user->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());$this->actingAs($user);}
    private function record(): SourceRecord {return SourceRecord::create(['source'=>'youtube','source_id'=>'original','title'=>'Accepted','body'=>'Ein ausführlicher Originalbeitrag über Hoffnung und den Glauben.','kind'=>'post','status'=>'ready','metadata'=>['tags'=>['Owner'],'media_ids'=>['original-file']]]);}
    private function classifier(): AiContentClassifier
    {
        $mock=\Mockery::mock(AiContentClassifier::class);$mock->shouldReceive('available')->andReturn(true);
        $mock->shouldReceive('classify')->andReturn(['title'=>'AI title','summary'=>'AI summary','tags'=>['AI tag'],'target_profile'=>'posts','confidence'=>0.97]);app()->instance(AiContentClassifier::class,$mock);return $mock;
    }
    public function test_record_history_and_undo_restore_pre_queue_state_without_deleting_data(): void
    {
        $this->owner();$record=$this->record();$classifier=$this->classifier();
        $this->postJson('/desktop/content/classify',['type'=>'record','id'=>(string)$record->id])->assertOk();
        $job=Queue::pushed(ClassifyImportedContent::class)->last();$job->handle($classifier,app(ContentAssignment::class));$record->refresh();$log=$record->classifications()->firstOrFail();
        $this->assertSame('applied',$log->status);$this->assertSame('AI title',$record->title);$this->assertSame(['original-file'],$record->metadata['media_ids']);
        $url='/desktop/content/'.$record->id.'/classifications/'.$log->id.'/undo';
        $this->getJson('/desktop/content/'.$record->id)->assertJsonPath('classifications.0.undo_url',url($url));
        $this->postJson($url)->assertOk();$record->refresh();$this->assertSame('Accepted',$record->title);$this->assertSame('ready',$record->status);$this->assertSame(['Owner'],$record->metadata['tags']);$this->assertSame('undone',$log->fresh()->status);$this->assertDatabaseCount('source_records',1);
        $this->postJson($url)->assertConflict();
    }
    public function test_undo_rejects_later_edits_and_wrong_record_and_requires_permission(): void
    {
        $this->owner();$record=$this->record();$classifier=$this->classifier();$record->update(['status'=>'unsorted']);(new ClassifyImportedContent('record',(string)$record->id))->handle($classifier,app(ContentAssignment::class));$log=$record->classifications()->firstOrFail();
        $other=SourceRecord::create(['source'=>'youtube','source_id'=>'other','kind'=>'post','title'=>'Other','metadata'=>[]]);
        $this->postJson('/desktop/content/'.$other->id.'/classifications/'.$log->id.'/undo')->assertNotFound();
        $record->refresh()->update(['body'=>'Owner correction']);$url='/desktop/content/'.$record->id.'/classifications/'.$log->id.'/undo';
        $this->postJson($url)->assertConflict();$this->assertSame('Owner correction',$record->fresh()->body);$this->assertSame('applied',$log->fresh()->status);
        $user=\App\Models\User::factory()->create();$this->actingAs($user)->postJson($url)->assertForbidden();
    }
    public function test_queued_job_does_not_apply_after_intervening_edit_and_insufficient_data_is_logged(): void
    {
        $this->owner();$record=$this->record();$classifier=\Mockery::mock(AiContentClassifier::class);$classifier->shouldReceive('available')->andReturn(true);$classifier->shouldNotReceive('classify');
        $record->update(['status'=>'unsorted']);$version=app(\App\Services\Importing\ContentState::class)->version($record);$record->update(['body'=>'Manual newer text']);
        (new ClassifyImportedContent('record',(string)$record->id,null,$version))->handle($classifier,app(ContentAssignment::class));$this->assertSame('Manual newer text',$record->fresh()->body);$this->assertSame(0,$record->classifications()->count());
        $record->update(['body'=>'','metadata'=>[]]);(new ClassifyImportedContent('record',(string)$record->id))->handle($classifier,app(ContentAssignment::class));
        $this->assertSame('insufficient_data',$record->classifications()->firstOrFail()->status);$this->assertSame('needs_attention',$record->fresh()->status);
    }
}
