<?php

namespace Tests\Feature;

use App\Models\{Media, Publication, SourceRecord};
use App\Services\Settings;
use App\Services\Publishing\{ConnectionStore, PublishingService, YouTubeClient};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue, Storage};
use Tests\TestCase;

class PublishingConnectorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('media-canonical');
        Http::preventStrayRequests();
    }

    private function connect(string $provider): void
    {
        app(Settings::class)->update(['social_connections' => [$provider => ['external_id' => 'account']]]);
        app(ConnectionStore::class)->saveCredentials($provider, ['access_token' => 'token']);
    }

    private function publication(string $provider, bool $withVideo = true): Publication
    {
        $ids = [];
        foreach ($withVideo ? ['image', 'video'] : ['image'] as $kind) {
            $extension = $kind === 'video' ? 'mp4' : 'jpg';
            $path = hash('sha256', $kind).'.'.$extension;
            Storage::disk('media-canonical')->put($path, $kind);
            $media = Media::create(['source' => 'upload', 'source_id' => $kind, 'title' => $kind, 'disk' => 'media-canonical',
                'path' => $path, 'original_name' => $path, 'kind' => $kind, 'mime' => $kind === 'video' ? 'video/mp4' : 'image/jpeg', 'bytes' => strlen($kind)]);
            $ids[] = $media->id;
        }
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'record', 'kind' => $withVideo ? 'video' : 'post',
            'title' => 'Publication', 'status' => 'ready', 'metadata' => ['media_ids' => $ids]]);
        return Publication::create(['source_record_id' => $record->id, 'provider' => $provider, 'direction' => 'outbound', 'status' => 'queued']);
    }

    public function test_instagram_uses_video_not_cover_and_reuses_container_after_processing(): void
    {
        $this->connect('instagram');
        $publication = $this->publication('instagram');
        Http::fake([
            'https://graph.facebook.com/*/account/media' => Http::response(['id' => 'container']),
            'https://graph.facebook.com/*/container*' => Http::sequence()->push(['status_code' => 'IN_PROGRESS'])->push(['status_code' => 'FINISHED']),
            'https://graph.facebook.com/*/account/media_publish' => Http::response(['id' => 'published-id']),
            'https://graph.facebook.com/*/published-id*' => Http::response(['permalink' => 'https://www.instagram.com/p/actual-shortcode/']),
        ]);
        $service = app(PublishingService::class);
        $service->execute($publication->id);
        $this->assertSame('queued', $publication->fresh()->status);
        $this->assertSame('container', $publication->fresh()->payload['container_id']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/account/media')
            && $request['media_type'] === 'REELS' && str_ends_with($request['video_url'], '.mp4') && ! isset($request['image_url']));
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/media_publish'));
        $this->travel(31)->seconds();
        $this->assertSame(1, $service->dispatchDue());
        $service->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertSame('https://www.instagram.com/p/actual-shortcode/', $publication->fresh()->external_url);
        Http::assertSentCount(5);
    }

    public function test_instagram_image_publication_uses_image_url(): void
    {
        $this->connect('instagram');
        $publication = $this->publication('instagram', false);
        Http::fake([
            'https://graph.facebook.com/*/account/media' => Http::response(['id' => 'container']),
            'https://graph.facebook.com/*/container*' => Http::response(['status_code' => 'FINISHED']),
            'https://graph.facebook.com/*/account/media_publish' => Http::response(['id' => 'published-id']),
            'https://graph.facebook.com/*/published-id*' => Http::response(['permalink' => 'https://www.instagram.com/p/photo/']),
        ]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/account/media') && isset($request['image_url']) && ! isset($request['video_url']));
    }

    public function test_reusable_youtube_stream_is_created_once_and_kept_encrypted(): void
    {
        $this->connect('youtube');
        Http::fake(['https://www.googleapis.com/youtube/v3/liveStreams*' => Http::response([
            'id' => 'reusable-id', 'cdn' => ['ingestionInfo' => ['ingestionAddress' => 'rtmp://a.rtmp.youtube.com/live2', 'streamName' => 'secret-key']],
        ])]);
        $client = app(YouTubeClient::class);
        $first = $client->reusableStream();
        $this->assertSame($first, $client->reusableStream());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['contentDetails']['isReusable'] === true);
        $stored = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'secret.social_youtube')->value('value');
        $this->assertStringNotContainsString('secret-key', $stored);
        $this->assertStringNotContainsString('rtmp://', $stored);
    }

    public function test_youtube_uploads_send_file_bytes_not_an_empty_json_body(): void
    {
        $this->connect('youtube');
        Storage::disk('media-canonical')->put('video.mp4', 'actual-video-bytes');
        Storage::disk('media-canonical')->put('cover.jpg', 'actual-image-bytes');
        $bodies = [];
        Http::fake([
            'https://www.googleapis.com/upload/youtube/v3/videos*' => Http::response([], 200, ['Location' => 'https://www.googleapis.com/upload/session']),
            'https://www.googleapis.com/upload/session' => function ($request) use (&$bodies) {
                $bodies['video'] = $request->body();
                return Http::response(['id' => 'video-id', 'status' => ['privacyStatus' => 'public']]);
            },
            'https://www.googleapis.com/upload/youtube/v3/thumbnails/set*' => function ($request) use (&$bodies) {
                $bodies['image'] = $request->body();
                return Http::response([]);
            },
        ]);
        $client = app(YouTubeClient::class);
        $client->uploadVideo(Storage::disk('media-canonical')->path('video.mp4'), ['title' => 'Video'], ['privacyStatus' => 'public'], $this->publication('youtube'));
        $client->uploadThumbnail('video-id', Storage::disk('media-canonical')->path('cover.jpg'));
        $this->assertSame(['video' => 'actual-video-bytes', 'image' => 'actual-image-bytes'], $bodies);
    }

    public function test_youtube_completion_checks_remote_lifecycle_before_transition(): void
    {
        $this->connect('youtube');
        Http::fake([
            'https://www.googleapis.com/youtube/v3/liveBroadcasts?*' => Http::sequence()
                ->push(['items' => [['status' => ['lifeCycleStatus' => 'complete']]]])
                ->push(['items' => [['status' => ['lifeCycleStatus' => 'ready']]]])
                ->push(['items' => [['status' => ['lifeCycleStatus' => 'live']]]]),
            'https://www.googleapis.com/youtube/v3/liveBroadcasts/transition*' => Http::response(['id' => 'broadcast']),
        ]);
        $client = app(YouTubeClient::class);
        $this->assertSame('ended', $client->completeBroadcast('broadcast'));
        $this->assertSame('not_started', $client->completeBroadcast('broadcast'));
        $this->assertSame('ended', $client->completeBroadcast('broadcast'));
        Http::assertSentCount(4);
    }

    public function test_facebook_and_telegram_do_not_upload_live_as_a_video(): void
    {
        foreach (['facebook', 'telegram'] as $provider) {
            $this->connect($provider);
            $record = SourceRecord::create(['source' => 'website', 'source_id' => $provider, 'kind' => 'video',
                'title' => 'Live', 'status' => 'ready', 'metadata' => ['public_section' => 'live']]);
            $publication = Publication::create(['source_record_id' => $record->id, 'provider' => $provider, 'direction' => 'outbound', 'status' => 'queued']);
            app(PublishingService::class)->execute($publication->id);
            $this->assertSame('skipped', $publication->fresh()->status);
        }
        Http::assertNothingSent();
    }
}
