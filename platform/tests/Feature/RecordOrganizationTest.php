<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Models\SourceRecord;
use App\Models\Media;
class RecordOrganizationTest extends TestCase
{
    use RefreshDatabase;
    private function owner(): void {Queue::fake();app(\App\Services\Access::class)->seed();$user=\App\Models\User::factory()->create();$user->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());$this->actingAs($user);}
    public function test_bulk_changes_section_and_adds_tags_without_replacing_text_or_losing_links(): void
    {
        $this->owner();$record=SourceRecord::create(['source'=>'youtube','source_id'=>'original','title'=>'Accepted title','body'=>'Original text','kind'=>'video','status'=>'ready','metadata'=>['tags'=>['Old'],'media_ids'=>['original-file']]]);
        $this->patchJson('/desktop/content/organize',['ids'=>[$record->id],'target_profile'=>'media_library','add_tags'=>['New']])->assertOk()->assertJsonPath('count',1);
        $record->refresh();$this->assertSame('Accepted title',$record->title);$this->assertSame('Original text',$record->body);$this->assertSame(['Old','New'],$record->metadata['tags']);$this->assertSame(['original-file'],$record->metadata['media_ids']);
        $this->getJson('/desktop/content?section=videos')->assertJsonCount(0,'data');$this->getJson('/desktop/content')->assertJsonCount(1,'data');
        $this->patchJson('/desktop/content/organize',['ids'=>[$record->id],'target_profile'=>'shorts','status'=>'unsorted'])->assertOk();$this->assertSame('short',$record->fresh()->kind);$this->getJson('/desktop/content?section=videos')->assertJsonCount(1,'data');
        $this->patchJson('/desktop/content/organize',['ids'=>[$record->id,9999],'status'=>'ready'])->assertNotFound();$this->assertSame('unsorted',$record->fresh()->status);
    }
    public function test_canonical_video_can_return_to_library_and_back_without_duplicate_or_text_change(): void
    {
        $this->owner();$media=Media::create(['source'=>'youtube','title'=>'File title','original_name'=>'video.mp4','disk'=>'private','path'=>'video.mp4','kind'=>'video','mime'=>'video/mp4','bytes'=>5,'status'=>'ready','metadata'=>[]]);
        $record=SourceRecord::create(['source'=>'youtube','source_id'=>'canonical','title'=>'Accepted video','body'=>'Description','kind'=>'video','status'=>'ready','metadata'=>['media_ids'=>[$media->id]]]);
        $assignment=app(\App\Services\Importing\ContentAssignment::class);$assignment->media($media,['target_profile'=>'media_library']);$this->assertTrue($record->fresh()->metadata['library_only']);
        $assignment->media($media,['target_profile'=>'shorts']);$this->assertFalse($record->fresh()->metadata['library_only']);$this->assertSame('short',$record->fresh()->kind);$this->assertSame('Accepted video',$record->fresh()->title);$this->assertSame('Description',$record->fresh()->body);$this->assertDatabaseCount('source_records',1);
        $user=\App\Models\User::factory()->create();$this->actingAs($user)->patchJson('/desktop/content/organize',['ids'=>[$record->id],'status'=>'ready'])->assertForbidden();
    }
}
