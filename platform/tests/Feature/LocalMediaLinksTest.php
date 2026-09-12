<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\Importing\LocalMediaLinks;
class LocalMediaLinksTest extends TestCase
{
    use RefreshDatabase;
    public function test_identifier_repair_connects_existing_video_and_cover_without_overwriting_edits(): void
    {
        Queue::fake();Storage::fake('private');Storage::disk('private')->put('video.mp4','video');
        $attributes=['source'=>'youtube','source_id'=>'abc_defghij:file','title'=>'Original','original_name'=>'video.mp4','disk'=>'private','path'=>'video.mp4','bytes'=>5,'kind'=>'video','mime'=>'video/mp4','status'=>'unsorted','metadata'=>[]];
        $video=Media::create($attributes);
        $wrong=Media::create([...$attributes,'source_id'=>'abcXdefghij:other','path'=>'other.mp4']);
        $cover=Media::create([...$attributes,'source_id'=>'abc_defghij:cover','path'=>'cover.png','kind'=>'image','mime'=>'image/png','asset_role'=>'thumbnail']);
        $record=SourceRecord::create(['source'=>'youtube','source_id'=>'abc_defghij','kind'=>'short','title'=>'Accepted','body'=>'Owner description','status'=>'ready','metadata'=>['cover_media_id'=>$cover->id]]);
        app(LocalMediaLinks::class)->repair($record);$record->refresh();
        $this->assertContains($video->id,$record->metadata['media_ids']);$this->assertNotContains($wrong->id,$record->metadata['media_ids']);
        $this->assertSame('Accepted',$record->title);$this->assertSame('Owner description',$record->body);$this->assertSame('short',$record->kind);
        $this->assertSame($video->id,$cover->fresh()->parent_id);$this->assertSame($cover->id,$video->fresh()->metadata['cover_media_id']);
        app(LocalMediaLinks::class)->repair($record);$this->assertDatabaseCount('media_usages',2);
        $this->assertTrue(app(\App\Services\Importing\ImportedContentPresentation::class)->assets($record)->firstWhere('id',$video->id)['available']);
    }
    public function test_link_actions_require_both_permissions_and_reject_non_video(): void
    {
        Queue::fake();app(\App\Services\Access::class)->seed();$user=\App\Models\User::factory()->create();$this->actingAs($user);
        $this->postJson('/desktop/content/local-links')->assertForbidden();
        $user->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
        $this->postJson('/desktop/content/local-links')->assertOk();Queue::assertPushed(\App\Jobs\RepairLocalMediaLinks::class);
        $record=SourceRecord::create(['source'=>'upload','source_id'=>'post','kind'=>'post','title'=>'Post','metadata'=>[]]);
        $media=Media::create(['source'=>'upload','title'=>'Image','original_name'=>'x.png','disk'=>'private','path'=>'x.png','bytes'=>1,'kind'=>'image','mime'=>'image/png','status'=>'unsorted']);
        $this->postJson('/desktop/content/'.$record->id.'/local-video',['media_id'=>$media->id])->assertUnprocessable();
    }
}
