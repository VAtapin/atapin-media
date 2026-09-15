<?php

namespace Tests\Feature;

use App\Models\{Media, Publication, Role, SourceRecord, User};
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentLibraryPublicationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_library_can_find_published_content_across_content_kinds(): void
    {
        app(Access::class)->seed();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($user);

        SourceRecord::create([
            'source' => 'upload', 'source_id' => 'published-video', 'kind' => 'video',
            'title' => 'Published video', 'body' => 'Video', 'status' => 'ready',
            'metadata' => ['public_published' => true, 'public_homepage' => true],
        ]);
        SourceRecord::create([
            'source' => 'upload', 'source_id' => 'published-post', 'kind' => 'post',
            'title' => 'Published post', 'body' => 'Article', 'status' => 'ready',
            'metadata' => ['public_published' => true],
        ]);
        SourceRecord::create([
            'source' => 'upload', 'source_id' => 'private-video', 'kind' => 'video',
            'title' => 'Private video', 'body' => 'Not published', 'status' => 'ready',
            'metadata' => [],
        ]);

        $response = $this->getJson('/desktop/content?publication=published');

        $response->assertOk()->assertJsonCount(2, 'data');
        $byTitle = collect($response->json('data'))->keyBy('title');
        $this->assertTrue($byTitle['Published post']['public_published']);
        $this->assertFalse($byTitle['Published post']['public_homepage']);
        $this->assertTrue($byTitle['Published video']['public_published']);
        $this->assertTrue($byTitle['Published video']['public_homepage']);
        $this->getJson('/desktop/content?publication=unpublished')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Private video');
    }

    public function test_content_library_returns_the_linked_cover_for_video_cards(): void
    {
        app(Access::class)->seed();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($user);

        $cover = Media::create([
            'source' => 'upload', 'source_id' => 'video-cover', 'title' => 'Cover',
            'original_name' => 'cover.jpg', 'kind' => 'image', 'mime' => 'image/jpeg',
            'disk' => 'local', 'path' => 'cover.jpg', 'bytes' => 10, 'status' => 'ready',
        ]);
        $video = Media::create([
            'source' => 'upload', 'source_id' => 'card-video-file', 'title' => 'Video',
            'original_name' => 'video.mp4', 'kind' => 'video', 'mime' => 'video/mp4',
            'disk' => 'local', 'path' => 'video.mp4', 'bytes' => 100, 'status' => 'ready',
        ]);
        SourceRecord::create([
            'source' => 'upload', 'source_id' => 'card-video', 'kind' => 'video',
            'title' => 'Card video', 'body' => 'Description', 'status' => 'ready',
            'metadata' => ['cover_media_id' => $cover->id, 'media_ids' => [$cover->id, $video->id]],
        ]);

        $this->getJson('/desktop/content?section=videos')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Card video')
            ->assertJsonPath('data.0.cover_url', route('media.preview', $cover))
            ->assertJsonPath('data.0.video_url', route('media.preview', $video));
    }

    public function test_content_library_exposes_external_publication_badges_without_website_status(): void
    {
        app(Access::class)->seed();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($user);
        $video = SourceRecord::create(['source' => 'youtube', 'source_id' => 'external-video', 'kind' => 'video', 'title' => 'External video', 'body' => '', 'status' => 'ready', 'metadata' => ['public_published' => false]]);
        Publication::create(['source_record_id' => $video->id, 'provider' => 'youtube', 'direction' => 'outbound', 'status' => 'published', 'remote_status' => 'public', 'external_id' => 'external-id', 'external_url' => 'https://youtube.com/watch?v=external-id']);

        $this->getJson('/desktop/content?section=videos')->assertOk()->assertJsonPath('data.0.external_publications.0.provider', 'youtube')->assertJsonPath('data.0.external_publications.0.remote_status', 'public');
        $this->getJson(route('content.show', $video))->assertOk()->assertJsonPath('external_publications.0.external_url', 'https://youtube.com/watch?v=external-id');
    }
}
