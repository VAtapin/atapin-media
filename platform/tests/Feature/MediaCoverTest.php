<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\SourceRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaCoverTest extends TestCase
{
    use RefreshDatabase;
    private function login(string $role = 'Owner'): void
    {
        app(Access::class)->seed(); $user = User::factory()->create();
        $user->roles()->attach(Role::where('name',$role)->firstOrFail()); $this->actingAs($user);
    }
    private function media(string $kind): Media
    {
        $name = $kind.'-'.bin2hex(random_bytes(3)); Storage::disk('local')->put($name,'Original');
        return Media::create(['disk'=>'local','path'=>$name,'original_name'=>$name,'title'=>$name,'source'=>'upload',
            'kind'=>$kind,'mime'=>$kind === 'image' ? 'image/png' : 'video/mp4','bytes'=>8,'status'=>'ready']);
    }
    private function record(array $metadata = [], string $kind = 'video'): SourceRecord
    {
        return SourceRecord::create(['source'=>'youtube','source_id'=>bin2hex(random_bytes(6)), 'title'=>'Original title',
            'body'=>'Original description','kind'=>$kind,'status'=>'ready','metadata'=>$metadata]);
    }
    public function test_cover_links_to_content_with_or_without_local_video_without_copying_original(): void
    {
        $this->login(); Storage::fake('local'); $image = $this->media('image'); $record = $this->record();
        $url = '/desktop/media/'.$image->id.'/cover';
        $this->postJson($url,['record_id'=>$record->id])->assertOk();
        $this->getJson('/desktop/content/'.$record->id)->assertOk()->assertJsonPath('has_local_video',false)->assertJsonPath('assets.0.id',$image->id);
        $this->assertNull($image->fresh()->parent_id);
        $video = $this->media('video'); $record->refresh()->update(['metadata'=>['media_ids'=>[$video->id,$image->id]]]);
        $this->postJson($url,['record_id'=>$record->id])->assertOk();
        $this->assertSame($video->id,$image->fresh()->parent_id); $this->assertSame('thumbnail',$image->fresh()->asset_role);
        $this->getJson('/desktop/media/library?kind=video')->assertOk()->assertJsonPath('data.0.thumbnail_url',route('media.preview',$image));
        $this->getJson('/desktop/media/'.$image->id.'/details')->assertOk()->assertJsonPath('usages.0.title','Original title');
        $this->assertSame('Original description',$record->fresh()->body); $this->assertDatabaseCount('media',2);
        $this->assertDatabaseCount('media_usages',1); $this->assertTrue(Storage::disk('local')->exists($image->path));
    }
    public function test_cover_assignment_validates_types_permissions_and_existing_parent(): void
    {
        $this->login(); Storage::fake('local'); $image = $this->media('image'); $video = $this->media('video');
        $record = $this->record(['media_ids'=>[$video->id]]);
        $this->postJson('/desktop/media/'.$video->id.'/cover',['record_id'=>$record->id])->assertUnprocessable();
        $this->postJson('/desktop/media/'.$image->id.'/cover',['record_id'=>$this->record([], 'post')->id])->assertUnprocessable();
        $image->update(['parent_id'=>$this->media('video')->id]);
        $this->postJson('/desktop/media/'.$image->id.'/cover',['record_id'=>$record->id])->assertConflict();
        $this->login('Support'); $this->postJson('/desktop/media/'.$image->id.'/cover',['record_id'=>$record->id])->assertForbidden();
    }
}
