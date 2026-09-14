<?php

namespace Tests\Feature;

use App\Models\{Media, Publication, Role, SourceRecord, User};
use App\Services\{Access, Settings};
use App\Services\Publishing\{ConnectionStore, MediaResolver, PublishingService, VideoRenderer, YouTubeClient};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, Http, Queue, Storage};
use Tests\TestCase;

class PublishingAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('media-canonical');
        Http::preventStrayRequests();
        app(Access::class)->seed();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($user);
        app(Settings::class)->update(['social_connections' => ['youtube' => ['external_id' => 'channel'], 'x' => ['external_id' => 'x-user']]]);
        foreach (['youtube', 'x'] as $provider) app(ConnectionStore::class)->saveCredentials($provider, ['access_token' => 'secret-token']);
    }

    private function publication(string $provider = 'youtube', string $kind = 'video'): Publication
    {
        $record = SourceRecord::create(['source' => 'website', 'source_id' => uniqid(), 'kind' => $kind, 'title' => 'Title', 'body' => 'Body', 'status' => 'ready',
            'metadata' => ['public_published' => true, 'publishing_targets' => ['website', $provider]]]);
        return Publication::where('source_record_id', $record->id)->where('provider', $provider)->firstOrFail();
    }

    public function test_youtube_resumes_confirmed_bytes_and_encrypts_the_session(): void
    {
        $publication = $this->publication();
        Storage::disk('media-canonical')->put('video.mp4', 'abcdefghij');
        $path = Storage::disk('media-canonical')->path('video.mp4');
        $publication->update(['payload' => ['upload_session' => Crypt::encryptString(json_encode(['url' => 'https://www.googleapis.com/upload/session-secret', 'path' => $path, 'size' => 10]))]]);
        $bodies = [];
        Http::fake(['https://www.googleapis.com/upload/session-secret' => function ($request) use (&$bodies) {
            $bodies[] = [$request->header('Content-Range')[0], $request->body()];
            return count($bodies) === 1 ? Http::response([], 308, ['Range' => 'bytes=0-3']) : Http::response(['id' => 'video-id', 'status' => ['privacyStatus' => 'public']]);
        }]);
        $result = app(YouTubeClient::class)->uploadVideo($path, ['title' => 'Title'], ['privacyStatus' => 'public'], $publication);
        $this->assertSame('video-id', $result['id']);
        $this->assertSame([['bytes */10', ''], ['bytes 4-9/10', 'efghij']], $bodies);
        $this->assertStringNotContainsString('session-secret', json_encode($publication->fresh()->payload));
        $this->getJson(route('desktop.publishing.index'))->assertDontSee('session-secret');
        Http::assertSentCount(2);
    }

    public function test_lost_final_response_is_recovered_without_another_upload(): void
    {
        $publication = $this->publication();
        Storage::disk('media-canonical')->put('video.mp4', 'bytes');
        $path = Storage::disk('media-canonical')->path('video.mp4');
        $publication->update(['payload' => ['upload_session' => Crypt::encryptString(json_encode(['url' => 'https://www.googleapis.com/upload/session', 'path' => $path, 'size' => 5]))]]);
        Http::fake(['https://www.googleapis.com/upload/session' => Http::response(['id' => 'already-uploaded', 'status' => ['privacyStatus' => 'public']])]);
        $this->assertSame('already-uploaded', app(YouTubeClient::class)->uploadVideo($path, [], [], $publication)['id']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->body() === '');
    }

    public function test_expired_session_is_not_silently_replaced(): void
    {
        $publication = $this->publication();
        Storage::disk('media-canonical')->put('video.mp4', 'bytes');
        $path = Storage::disk('media-canonical')->path('video.mp4');
        $publication->update(['payload' => ['upload_session' => Crypt::encryptString(json_encode(['url' => 'https://www.googleapis.com/upload/session', 'path' => $path, 'size' => 5]))]]);
        Http::fake(['https://www.googleapis.com/upload/session' => Http::response([], 404)]);
        $this->expectExceptionMessage('checkpoint has been retained');
        app(YouTubeClient::class)->uploadVideo($path, [], [], $publication);
    }

    public function test_youtube_processing_is_pending_not_published_and_reuses_video_id(): void
    {
        $publication = $this->publication();
        $resolver = $this->mock(MediaResolver::class);
        $resolver->shouldReceive('video')->once()->andReturn(['path' => 'video.mp4']);
        $resolver->shouldReceive('image')->andReturnNull();
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('uploadVideo')->once()->andReturn(['id' => 'video-id', 'status' => ['privacyStatus' => 'public', 'uploadStatus' => 'uploaded']]);
        $client->shouldReceive('video')->once()->andReturn(['status' => ['privacyStatus' => 'public', 'uploadStatus' => 'processed'], 'processingDetails' => ['processingStatus' => 'succeeded']]);
        $service = app(PublishingService::class);
        $service->execute($publication->id);
        $this->assertSame('queued', $publication->fresh()->status);
        $this->assertNull($publication->fresh()->published_at);
        $this->assertSame('video-id', $publication->fresh()->external_id);
        $this->travel(31)->seconds();
        $service->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
    }

    public function test_youtube_processing_failure_is_reported(): void
    {
        $publication = $this->publication();
        $publication->update(['external_id' => 'video-id']);
        $this->mock(YouTubeClient::class)->shouldReceive('video')->andReturn(['status' => ['privacyStatus' => 'public'], 'processingDetails' => ['processingStatus' => 'failed']]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('failed', $publication->fresh()->status);
        $this->assertNull($publication->fresh()->published_at);
    }

    public function test_video_beitrag_is_uploaded_as_video_without_rendering(): void
    {
        $publication = $this->publication('youtube', 'post');
        $resolver = $this->mock(MediaResolver::class);
        $resolver->shouldReceive('video')->once()->andReturn(['path' => 'post.mp4']);
        $resolver->shouldReceive('image')->andReturnNull();
        $this->mock(VideoRenderer::class)->shouldNotReceive('video');
        $this->mock(YouTubeClient::class)->shouldReceive('uploadVideo')->once()->andReturn(['id' => 'post-video', 'status' => ['privacyStatus' => 'public']]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
    }

    public function test_image_or_text_beitrag_uses_the_video_adapter(): void
    {
        $publication = $this->publication('youtube', 'post');
        $resolver = $this->mock(MediaResolver::class);
        $resolver->shouldReceive('video')->andReturnNull();
        $resolver->shouldReceive('image')->andReturnNull();
        $this->mock(VideoRenderer::class)->shouldReceive('video')->once()->andReturn(['path' => 'derived.mp4']);
        $this->mock(YouTubeClient::class)->shouldReceive('uploadVideo')->once()->andReturn(['id' => 'derived', 'status' => ['privacyStatus' => 'public']]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('derived', $publication->fresh()->external_id);
    }

    public function test_daily_discovery_filters_old_videos_and_stops_pagination(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'uploads']]]]]),
            'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [
                ['contentDetails' => ['videoId' => 'new', 'videoPublishedAt' => now()->subHours(2)->toIso8601String()]],
                ['contentDetails' => ['videoId' => 'old', 'videoPublishedAt' => now()->subDays(2)->toIso8601String()]],
            ], 'nextPageToken' => 'older-page']),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [['id' => 'new']]]),
        ]);
        $this->assertSame([['id' => 'new']], app(YouTubeClient::class)->uploadedVideos());
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/videos') && $request['id'] === 'new');
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $event = collect($events)->first(fn ($event) => str_contains($event->command ?? '', 'publishing:youtube-sync'));
        $this->assertSame('0 0 * * *', $event->expression);
    }

    public function test_rtmp_destinations_are_encrypted_live_only_and_removable(): void
    {
        $this->postJson(route('desktop.publishing.outputs.save'), ['id' => 'rtmp_facebook', 'label' => 'Facebook Live', 'url' => 'rtmps://live.example/live/secret-key'])->assertOk();
        $this->assertStringNotContainsString('secret-key', \Illuminate\Support\Facades\DB::table('settings')->where('key', 'secret.live_outputs')->value('value'));
        $response = $this->getJson(route('desktop.publishing.index'))->assertOk()->assertDontSee('secret-key')->assertDontSee('live.example');
        $destination = collect($response->json('destinations'))->firstWhere('provider', 'rtmp_facebook');
        $this->assertTrue($destination['capabilities']['live']);
        $this->assertFalse($destination['capabilities']['video']);
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'live', 'kind' => 'video', 'title' => 'Live', 'status' => 'ready', 'metadata' => ['public_section' => 'live']]);
        $this->postJson(route('desktop.publishing.publish'), ['record_id' => $record->id, 'destinations' => ['website', 'rtmp_facebook']])->assertOk();
        $publication = Publication::where('provider', 'rtmp_facebook')->firstOrFail();
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('scheduled', $publication->fresh()->remote_status);
        $this->deleteJson(route('desktop.publishing.outputs.remove', 'rtmp_facebook'))->assertOk();
        $this->assertFalse(app(ConnectionStore::class)->connected('rtmp_facebook'));
    }

    public function test_rtmp_rejects_non_rtmp_urls(): void
    {
        $this->postJson(route('desktop.publishing.outputs.save'), ['id' => 'rtmp_bad', 'label' => 'Bad', 'url' => 'file:///secret'])->assertUnprocessable();
    }

    public function test_destination_configuration_requires_integration_permission(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('desktop.publishing.outputs.save'), ['id' => 'rtmp_test', 'label' => 'Test', 'url' => 'rtmp://example/live/key'])->assertForbidden();
        $this->get(route('desktop.publishing.x.connect'))->assertForbidden();
    }

    public function test_website_edits_update_the_existing_youtube_video(): void
    {
        $publication = $this->publication();
        $publication->update(['status' => 'published', 'external_id' => 'same-id', 'payload' => ['applied_signature' => app(PublishingService::class)->signature($publication->record)]]);
        $publication->record->update(['title' => 'New title']);
        $this->assertSame('update', $publication->fresh()->payload['action']);
        $this->mock(MediaResolver::class)->shouldReceive('image')->andReturnNull();
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('updateVideo')->once()->withArgs(fn ($id, $snippet, $privacy) => $id === 'same-id' && $snippet['title'] === 'New title' && $privacy === 'public');
        $client->shouldReceive('video')->andReturn(['status' => ['privacyStatus' => 'public']]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertSame('same-id', $publication->fresh()->external_id);
    }

    public function test_unpublishing_website_hides_youtube_without_deleting_it(): void
    {
        $publication = $this->publication();
        $publication->update(['status' => 'published', 'external_id' => 'same-id']);
        $record = $publication->record;
        $record->update(['metadata' => [...$record->metadata, 'public_published' => false]]);
        $this->assertSame('hide', $publication->fresh()->payload['action']);
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('updateVideo')->once()->withArgs(fn ($id, $snippet, $privacy) => $privacy === 'private');
        $client->shouldReceive('video')->andReturn(['status' => ['privacyStatus' => 'private']]);
        $client->shouldNotReceive('deleteVideo');
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('hidden', $publication->fresh()->remote_status);
    }

    public function test_imported_youtube_material_is_not_modified_remotely_by_website_edits(): void
    {
        $publication = $this->publication();
        $publication->update(['status' => 'published', 'external_id' => 'external-id', 'payload' => ['origin' => 'youtube_sync']]);
        $publication->record->update(['title' => 'Local title']);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertArrayNotHasKey('action', $publication->fresh()->payload);
    }

    public function test_remote_deletion_requires_confirmation_and_retains_local_record(): void
    {
        $publication = $this->publication();
        $publication->update(['status' => 'published', 'external_id' => 'same-id']);
        $this->deleteJson(route('desktop.publishing.remove', $publication))->assertUnprocessable();
        $this->deleteJson(route('desktop.publishing.remove', $publication), ['confirm' => true])->assertOk();
        $this->mock(YouTubeClient::class)->shouldReceive('deleteVideo')->once()->with('same-id');
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('deleted', $publication->fresh()->remote_status);
        $this->assertNotNull(SourceRecord::find($publication->source_record_id));
    }

    public function test_x_posts_text_and_checks_the_result(): void
    {
        $publication = $this->publication('x', 'post');
        Http::fake([
            'https://api.x.com/2/users/me*' => Http::response(['data' => ['id' => 'user', 'protected' => false]]),
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-id']]),
            'https://api.x.com/2/tweets/post-id' => Http::response(['data' => ['id' => 'post-id']]),
        ]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertSame('https://x.com/i/status/post-id', $publication->fresh()->external_url);
        Http::assertSentCount(3);
    }

    public function test_x_rejects_protected_accounts(): void
    {
        $publication = $this->publication('x', 'post');
        Http::fake(['https://api.x.com/2/users/me*' => Http::response(['data' => ['protected' => true]])]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('failed', $publication->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_x_oauth_uses_pkce_and_single_use_state(): void
    {
        config(['publishing.x.client_id' => 'client']);
        $response = $this->get(route('desktop.publishing.x.connect'))->assertRedirect();
        $this->assertStringContainsString('code_challenge_method=S256', $response->headers->get('Location'));
        $this->get(route('desktop.publishing.x.callback', ['state' => 'wrong', 'code' => 'code']))->assertStatus(419);
        $this->get(route('desktop.publishing.x.callback', ['state' => '', 'code' => 'code']))->assertStatus(419);
    }

    public function test_x_video_processing_reuses_uploaded_segments_and_posts_once(): void
    {
        $publication = $this->publication('x');
        Storage::disk('media-canonical')->put('x.mp4', 'video-bytes');
        $media = Media::create(['source' => 'upload', 'source_id' => 'x-video', 'kind' => 'video', 'mime' => 'video/mp4', 'bytes' => 11, 'disk' => 'media-canonical', 'path' => 'x.mp4', 'title' => 'Video', 'original_name' => 'x.mp4']);
        $record = $publication->record;
        $record->update(['metadata' => [...$record->metadata, 'media_ids' => [$media->id]]]);
        Http::fake([
            'https://api.x.com/2/users/me*' => Http::response(['data' => ['id' => 'user', 'protected' => false]]),
            'https://api.x.com/2/media/upload/initialize' => Http::response(['data' => ['id' => 'media-id']]),
            'https://api.x.com/2/media/upload/media-id/append' => Http::response([]),
            'https://api.x.com/2/media/upload/media-id/finalize' => Http::response(['data' => ['processing_info' => ['state' => 'pending']]]),
            'https://api.x.com/2/media/upload?*' => Http::response(['data' => ['processing_info' => ['state' => 'succeeded']]]),
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-id']]),
            'https://api.x.com/2/tweets/post-id' => Http::response(['data' => ['id' => 'post-id']]),
        ]);
        $service = app(PublishingService::class);
        $service->execute($publication->id);
        $this->assertSame('queued', $publication->fresh()->status);
        $this->assertSame(11, $publication->fresh()->payload['x_bytes']);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.x.com/2/tweets');
        $this->travel(31)->seconds();
        $service->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        Http::assertSentCount(8);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.x.com/2/tweets' && $request['media']['media_ids'] === ['media-id']);
    }

    public function test_x_oauth_callback_stores_credentials_encrypted_and_disconnect_clears_them(): void
    {
        config(['publishing.x.client_id' => 'client']);
        Http::fake([
            'https://api.x.com/2/oauth2/token' => Http::response(['access_token' => 'x-access-secret', 'refresh_token' => 'x-refresh-secret']),
            'https://api.x.com/2/users/me*' => Http::response(['data' => ['id' => '123', 'username' => 'owner', 'protected' => false]]),
        ]);
        $this->withSession(['publishing.x.oauth' => ['state' => 'state', 'verifier' => 'verifier']])->get(route('desktop.publishing.x.callback', ['state' => 'state', 'code' => 'code']))->assertRedirect('/desktop');
        $this->assertSame('x-refresh-secret', app(ConnectionStore::class)->credentials('x')['refresh_token']);
        $stored = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'secret.social_x')->value('value');
        $this->assertStringNotContainsString('x-access-secret', $stored);
        $this->get(route('desktop.publishing.x.callback', ['state' => 'state', 'code' => 'code']))->assertStatus(419);
        $this->postJson(route('desktop.publishing.x.disconnect'))->assertOk();
        $this->assertFalse(app(ConnectionStore::class)->connected('x'));
        $this->assertSame([], app(ConnectionStore::class)->credentials('x'));
    }

    public function test_pending_checks_do_not_exhaust_failure_retry_budget(): void
    {
        $publication = $this->publication();
        $publication->update(['status' => 'processing', 'attempts' => 100, 'payload' => ['consecutive_failures' => 0]]);
        app(PublishingService::class)->fail($publication->id, new \RuntimeException('Temporary failure'));
        $this->assertNotNull($publication->fresh()->next_attempt_at);
        $this->assertSame(1, $publication->fresh()->payload['consecutive_failures']);
    }

    public function test_soft_deletion_hides_remote_video_and_restoration_republishes_it(): void
    {
        $publication = $this->publication();
        $publication->update(['status' => 'published', 'external_id' => 'same-id']);
        $record = $publication->record;
        $record->delete();
        $this->assertSame('hide', $publication->fresh()->payload['action']);
        $this->assertNotNull($publication->fresh()->record);
        $publication->refresh()->update(['status' => 'published', 'remote_status' => 'hidden', 'payload' => ['applied_signature' => app(PublishingService::class)->signature($record)]]);
        $record->restore();
        $this->assertSame('update', $publication->fresh()->payload['action']);
    }

    public function test_edit_during_processing_is_not_lost_or_processed_concurrently(): void
    {
        $publication = $this->publication();
        $publication->update(['external_id' => 'same-id']);
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('video')->once()->andReturnUsing(function () use ($publication) {
            $publication->record->update(['title' => 'Changed during request']);
            $this->assertSame('processing', $publication->fresh()->status);
            return ['status' => ['privacyStatus' => 'public']];
        });
        $this->mock(MediaResolver::class)->shouldReceive('image')->andReturnNull();
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('queued', $publication->fresh()->status);
        $this->assertSame('update', $publication->fresh()->payload['action']);
    }

    public function test_upload_does_not_send_credentials_to_a_foreign_location(): void
    {
        $publication = $this->publication();
        Storage::disk('media-canonical')->put('video.mp4', 'bytes');
        Http::fake(['https://www.googleapis.com/upload/youtube/v3/videos*' => Http::response([], 200, ['Location' => 'https://attacker.example/upload'])]);
        try { app(YouTubeClient::class)->uploadVideo(Storage::disk('media-canonical')->path('video.mp4'), [], [], $publication); $this->fail('Invalid session accepted.'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('invalid upload session', $error->getMessage()); }
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'attacker.example'));
    }

    public function test_x_oauth_refresh_keeps_publication_working(): void
    {
        $publication = $this->publication('x', 'post');
        config(['publishing.x.client_id' => 'client', 'publishing.x.client_secret' => 'x-client-secret']);
        app(ConnectionStore::class)->saveCredentials('x', ['expires_at' => now()->subMinute()->timestamp, 'refresh_token' => 'old-refresh']);
        Http::fake([
            'https://api.x.com/2/oauth2/token' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 7200]),
            'https://api.x.com/2/users/me*' => Http::response(['data' => ['protected' => false]]),
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'new-post']]),
            'https://api.x.com/2/tweets/new-post' => Http::response(['data' => ['id' => 'new-post']]),
        ]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertSame('new-refresh', app(ConnectionStore::class)->credentials('x')['refresh_token']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/users/me') && $request->hasHeader('Authorization', 'Bearer new-access'));
    }

    public function test_opt_in_policy_automatically_removes_x_post_when_website_is_disabled(): void
    {
        $publication = $this->publication('x', 'post');
        $publication->update(['status' => 'published', 'external_id' => 'post-id']);
        $record = $publication->record;
        $record->update(['metadata' => [...$record->metadata, 'remove_external_on_unpublish' => true, 'public_published' => false]]);
        $this->assertSame('delete', $publication->fresh()->payload['action']);
        Http::fake(['https://api.x.com/2/tweets/post-id' => Http::response(['data' => ['deleted' => true]])]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('deleted', $publication->fresh()->status);
        $this->assertNotNull(SourceRecord::find($record->id));
    }

    public function test_default_policy_does_not_destroy_x_post_on_website_unpublication(): void
    {
        $publication = $this->publication('x', 'post');
        $publication->update(['status' => 'published', 'external_id' => 'post-id']);
        $record = $publication->record;
        $record->update(['metadata' => [...$record->metadata, 'public_published' => false]]);
        $this->assertSame('published', $publication->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_explicit_republication_after_remote_deletion_starts_a_new_upload(): void
    {
        $publication = $this->publication();
        $publication->update(['status' => 'deleted', 'remote_status' => 'deleted', 'external_id' => 'deleted-id', 'payload' => ['upload_session' => 'old-session', 'video_id' => 'deleted-id']]);
        app(PublishingService::class)->queueForRecord($publication->record, ['youtube']);
        $this->assertSame('queued', $publication->fresh()->status);
        $this->assertNull($publication->fresh()->external_id);
        $this->assertArrayNotHasKey('upload_session', $publication->fresh()->payload);
    }

    public function test_website_status_tracks_unpublication_trash_and_restore(): void
    {
        $record = $this->publication()->record;
        $website = Publication::where('source_record_id', $record->id)->where('provider', 'website')->firstOrFail();
        $publishedAt = $website->published_at;
        $record->update(['metadata' => [...$record->metadata, 'public_published' => false]]);
        $this->assertSame('unpublished', $website->fresh()->status);
        $this->assertSame('unpublished', $website->fresh()->remote_status);
        $this->assertEquals($publishedAt, $website->fresh()->published_at);
        $record->update(['metadata' => [...$record->metadata, 'public_published' => true]]);
        $this->assertSame('published', $website->fresh()->status);
        $record->delete();
        $this->assertSame('unpublished', $website->fresh()->status);
        $record->restore();
        $this->assertSame('published', $website->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_publishing_api_returns_the_saved_destinations(): void
    {
        $record = $this->publication()->record;
        $record->update(['metadata' => [...$record->metadata, 'publishing_targets' => ['website', 'x']]]);
        $this->getJson(route('desktop.publishing.index'))->assertOk()->assertJsonPath('records.0.publishing_targets', ['website', 'x']);
        Http::assertNothingSent();
    }

    public function test_reenabling_website_republishes_the_existing_hidden_video(): void
    {
        $publication = $this->publication();
        $record = $publication->record;
        $record->update(['metadata' => ['public_published' => false, 'publishing_targets' => ['website', 'youtube']]]);
        $publication->update(['status' => 'published', 'external_id' => 'same-video', 'remote_status' => 'hidden', 'payload' => ['applied_signature' => app(PublishingService::class)->signature($record)]]);
        $record->update(['metadata' => [...$record->metadata, 'public_published' => true]]);
        $this->assertSame('queued', $publication->fresh()->status);
        $this->assertSame('update', $publication->fresh()->payload['action']);
        $this->assertSame('same-video', $publication->fresh()->external_id);
    }
}
