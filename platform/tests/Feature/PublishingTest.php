<?php

namespace Tests\Feature;

use App\Jobs\PublishToPlatform;
use App\Models\{Publication, Role, SourceRecord, User};
use App\Services\{Access, Settings};
use App\Services\Publishing\{ConnectionStore, MediaResolver, OAuthAppCredentials, PublishingService, YouTubeClient};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(Access::class)->seed();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($user);
        Queue::fake();
    }

    public function test_website_publication_queues_each_connected_destination(): void
    {
        $settings = app(Settings::class);
        $settings->update(['social_connections' => ['youtube' => ['external_id' => 'channel-id', 'public_url' => 'https://youtube.com/@manna']]]);
        $settings->updateSecrets(['social_youtube' => json_encode(['access_token' => 'encrypted-token'])]);
        $record = SourceRecord::create(['source' => 'upload', 'source_id' => 'video-1', 'kind' => 'video', 'title' => 'Video', 'body' => '', 'status' => 'ready', 'metadata' => ['public_published' => false]]);

        $metadata = $record->metadata;
        $metadata['public_published'] = true;
        $metadata['publishing_targets'] = ['website', 'youtube'];
        $record->update(['metadata' => $metadata]);

        $this->assertDatabaseHas('publications', ['source_record_id' => $record->id, 'provider' => 'website', 'status' => 'published']);
        $this->assertDatabaseHas('publications', ['source_record_id' => $record->id, 'provider' => 'youtube', 'status' => 'queued']);
        Queue::assertPushed(PublishToPlatform::class, fn (PublishToPlatform $job) => $job->publicationId > 0);
    }

    public function test_publishing_endpoint_requires_ready_content_and_returns_status(): void
    {
        $record = SourceRecord::create(['source' => 'upload', 'source_id' => 'draft-1', 'kind' => 'video', 'title' => 'Draft', 'body' => '', 'status' => 'unsorted', 'metadata' => []]);
        $this->postJson(route('desktop.publishing.publish'), ['record_id' => $record->id, 'destinations' => ['website']])->assertUnprocessable();
        $this->getJson(route('desktop.publishing.index'))->assertOk()->assertJsonStructure(['records', 'destinations', 'publications', 'youtube']);
    }

    public function test_youtube_oauth_requires_admin_config_and_uses_the_configured_callback(): void
    {
        $url = route('desktop.publishing.youtube.connect');
        $this->get($url)->assertStatus(503);
        app(OAuthAppCredentials::class)->save('youtube', ['client_id' => 'client-id', 'client_secret' => 'client-secret']);
        $response = $this->get($url)->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame(route('desktop.publishing.youtube.callback'), $query['redirect_uri']);
    }

    public function test_publishing_screen_explains_scope_and_loads_scrolling_styles(): void
    {
        $this->get('/desktop')->assertOk()
            ->assertSee(__('publishing.explanation_title'))
            ->assertSee(__('publishing.explanation_website'))
            ->assertSee(__('publishing.explanation_channels'))
            ->assertSee('desktop-publishing.css?v=3', false);
    }

    private function connectYouTube(): void
    {
        app(Settings::class)->update(['social_connections' => ['youtube' => ['external_id' => 'channel-id']]]);
        app(ConnectionStore::class)->saveCredentials('youtube', ['access_token' => 'test-token', 'refresh_token' => 'refresh-secret']);
        app(OAuthAppCredentials::class)->save('youtube', ['client_id' => 'client-id', 'client_secret' => 'client-secret']);
    }

    private function publication(array $metadata = []): Publication
    {
        $record = SourceRecord::create(['source' => 'website', 'source_id' => (string) \Illuminate\Support\Str::uuid(),
            'kind' => 'video', 'title' => 'Publishing test', 'status' => 'ready', 'metadata' => $metadata]);
        return Publication::create(['source_record_id' => $record->id, 'provider' => 'youtube', 'direction' => 'outbound', 'status' => 'queued']);
    }

    public function test_thumbnail_failure_preserves_video_id_and_retry_does_not_upload_again(): void
    {
        $this->connectYouTube();
        $publication = $this->publication(['youtube' => ['privacy_status' => 'private', 'publish_at' => '2030-01-01T00:00:00Z']]);
        $media = $this->mock(MediaResolver::class);
        $media->shouldReceive('video')->once()->andReturn(['path' => 'video.mp4']);
        $media->shouldReceive('image')->twice()->andReturn(['path' => 'cover.jpg']);
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('uploadVideo')->once()->withArgs(fn ($path, $snippet, $status) => $status['privacyStatus'] === 'public' && ! isset($status['publishAt']))
            ->andReturn(['id' => 'youtube-id', 'status' => ['privacyStatus' => 'public']]);
        $client->shouldReceive('uploadThumbnail')->once()->andThrow(new \RuntimeException('Thumbnail failed'));
        $client->shouldReceive('uploadThumbnail')->once()->andReturnNull();
        $client->shouldReceive('video')->once()->with('youtube-id')->andReturn(['status' => ['privacyStatus' => 'public']]);
        $service = app(PublishingService::class);
        $service->execute($publication->id);
        $this->assertSame('failed', $publication->fresh()->status);
        $this->assertSame('youtube-id', $publication->fresh()->external_id);
        $this->assertTrue($service->retry($publication->fresh()));
        $service->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertSame(2, $publication->fresh()->attempts);
        $this->assertFalse($service->retry($publication->fresh()));
        $service->execute($publication->id);
        $this->assertSame(2, $publication->fresh()->attempts);
    }

    public function test_youtube_private_response_is_not_reported_as_public_or_uploaded_twice(): void
    {
        $this->connectYouTube();
        $publication = $this->publication();
        $this->mock(MediaResolver::class)->shouldReceive('video')->once()->andReturn(['path' => 'video.mp4']);
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('uploadVideo')->once()->andReturn(['id' => 'private-id', 'status' => ['privacyStatus' => 'private']]);
        $client->shouldReceive('video')->once()->with('private-id')->andReturn(['status' => ['privacyStatus' => 'private']]);
        $service = app(PublishingService::class);
        $service->execute($publication->id);
        $this->assertSame('failed', $publication->fresh()->status);
        $this->assertSame('private-id', $publication->fresh()->external_id);
        $service->retry($publication->fresh());
        $service->execute($publication->id);
        $this->assertSame('failed', $publication->fresh()->status);
    }

    public function test_live_studio_video_kind_creates_broadcast_and_preserves_id_on_bind_failure(): void
    {
        $this->connectYouTube();
        $publication = $this->publication(['public_section' => 'live', 'live_status' => 'scheduled']);
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('reusableStream')->twice()->andReturn(['id' => 'one-stream']);
        $client->shouldReceive('createBroadcast')->once()->withArgs(fn ($snippet, $status) => $status['privacyStatus'] === 'public')
            ->andReturn(['id' => 'broadcast-id']);
        $client->shouldReceive('bindBroadcast')->once()->andThrow(new \RuntimeException('Temporary bind failure'));
        $client->shouldReceive('bindBroadcast')->once()->with('broadcast-id', 'one-stream')->andReturn([]);
        $this->mock(MediaResolver::class)->shouldReceive('image')->once()->andReturnNull();
        $service = app(PublishingService::class);
        $service->execute($publication->id);
        $this->assertSame('broadcast-id', $publication->fresh()->payload['broadcast_id']);
        $service->retry($publication->fresh());
        $service->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertTrue($publication->fresh()->payload['broadcast_bound']);
        $client->shouldReceive('configured')->once()->andReturnTrue();
        $this->getJson(route('desktop.publishing.index'))->assertOk()->assertJsonPath('records.0.kind', 'live');
    }

    public function test_due_retries_run_once_and_processing_cannot_be_retried(): void
    {
        $this->connectYouTube();
        $publication = $this->publication();
        $publication->update(['status' => 'failed', 'attempts' => 1, 'next_attempt_at' => now()->subSecond()]);
        $service = app(PublishingService::class);
        $this->assertSame(1, $service->dispatchDue());
        $this->assertSame(0, $service->dispatchDue());
        Queue::assertPushed(PublishToPlatform::class, 1);
        $publication->update(['status' => 'processing']);
        $this->postJson(route('desktop.publishing.retry', $publication))->assertStatus(409);
        $service->execute($publication->id);
        $this->assertSame(1, $publication->fresh()->attempts);
    }

    public function test_worker_failure_sets_retry_and_redacts_secrets(): void
    {
        $this->connectYouTube();
        $publication = $this->publication();
        $publication->update(['status' => 'processing', 'attempts' => 1]);
        (new PublishToPlatform($publication->id))->failed(new \RuntimeException('test-token refresh-secret client-secret https://api.telegram.org/bot123:secret/sendVideo'));
        $publication->refresh();
        $this->assertSame('failed', $publication->status);
        $this->assertNotNull($publication->next_attempt_at);
        $this->assertStringNotContainsString('test-token', $publication->error);
        $this->assertStringNotContainsString('refresh-secret', $publication->error);
        $this->assertStringNotContainsString('client-secret', $publication->error);
        $this->assertStringNotContainsString('123:secret', $publication->error);
        $publication->update(['status' => 'processing', 'attempts' => 5, 'payload' => [...$publication->payload, 'consecutive_failures' => 4]]);
        (new PublishToPlatform($publication->id))->failed(new \RuntimeException('Failed again'));
        $this->assertNull($publication->fresh()->next_attempt_at);
    }

    public function test_disconnect_clears_encrypted_credentials_and_blocks_queued_work_and_sync(): void
    {
        $this->connectYouTube();
        $publication = $this->publication();
        Http::preventStrayRequests();
        $this->postJson(route('desktop.publishing.youtube.disconnect'))->assertOk();
        $this->assertFalse(app(ConnectionStore::class)->connected('youtube'));
        $this->assertSame([], app(ConnectionStore::class)->credentials('youtube'));
        $this->assertFalse(app(YouTubeClient::class)->configured());
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('skipped', $publication->fresh()->status);
        $this->assertSame(0, app(\App\Services\Publishing\YouTubeInboundSync::class)->run());
        Http::assertNothingSent();
    }

    public function test_oauth_callback_requires_nonempty_matching_single_use_state(): void
    {
        Http::preventStrayRequests();
        $url = route('desktop.publishing.youtube.callback');
        $this->get($url)->assertStatus(419);
        $this->withSession(['publishing.youtube.oauth_state' => 'correct'])->get($url.'?state=wrong&code=code')->assertStatus(419);
        $this->withSession(['publishing.youtube.oauth_state' => 'correct'])->get($url.'?state=correct&error=access_denied')->assertRedirect('/desktop');
        $this->get($url.'?state=correct&code=code')->assertStatus(419);
        Http::assertNothingSent();
    }

    public function test_oauth_reconnect_to_another_channel_drops_previous_stream_and_refresh_token(): void
    {
        $this->connectYouTube();
        app(ConnectionStore::class)->saveCredentials('youtube', ['youtube_stream' => ['id' => 'old-stream']]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token', 'refresh_token' => 'new-refresh']),
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'new-channel', 'snippet' => ['customUrl' => '@new']]]]),
        ]);
        $this->withSession(['publishing.youtube.oauth_state' => 'state'])->get(route('desktop.publishing.youtube.callback').'?state=state&code=code')->assertRedirect('/desktop');
        $credentials = app(ConnectionStore::class)->credentials('youtube');
        $this->assertSame('new-token', $credentials['access_token']);
        $this->assertArrayNotHasKey('youtube_stream', $credentials);
        $this->assertSame('https://www.youtube.com/@new', app(ConnectionStore::class)->connection('youtube')['public_url']);
    }

    public function test_live_completion_failure_is_retried_without_creating_another_broadcast(): void
    {
        $this->connectYouTube();
        $publication = $this->publication(['public_section' => 'live', 'live_status' => 'ended']);
        $publication->update(['status' => 'published', 'external_id' => 'broadcast-id', 'payload' => ['broadcast_id' => 'broadcast-id']]);
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('completeBroadcast')->once()->andThrow(new \RuntimeException('Temporary completion failure'));
        $client->shouldReceive('completeBroadcast')->once()->with('broadcast-id')->andReturn('ended');
        $service = app(PublishingService::class);
        $service->queueLiveCompletion($publication->record);
        $service->execute($publication->id);
        $this->assertSame('failed', $publication->fresh()->status);
        $this->assertTrue($publication->fresh()->payload['live_end_requested']);
        $service->retry($publication->fresh());
        $service->execute($publication->id);
        $this->assertSame('ended', $publication->fresh()->remote_status);
        $service->queueLiveCompletion($publication->record);
        $this->assertSame('published', $publication->fresh()->status);
    }

    public function test_live_ending_between_connector_and_status_update_still_queues_completion(): void
    {
        $this->connectYouTube();
        $publication = $this->publication(['public_section' => 'live', 'live_status' => 'live']);
        $connector = \Mockery::mock(\App\Contracts\PublishingConnector::class);
        $connector->shouldReceive('publish')->once()->andReturnUsing(function () use ($publication) {
            $record = $publication->record;
            $record->update(['metadata' => [...$record->metadata, 'live_status' => 'ended']]);
            return ['external_id' => 'broadcast', 'remote_status' => 'scheduled', 'payload' => ['broadcast_id' => 'broadcast']];
        });
        $this->mock(\App\Services\Publishing\ConnectorRegistry::class)->shouldReceive('get')->once()->with('youtube')->andReturn($connector);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('queued', $publication->fresh()->status);
        $this->assertTrue($publication->fresh()->payload['live_end_requested']);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing::class, new PublishToPlatform($publication->id));
        Queue::assertPushed(PublishToPlatform::class, 1);
    }
}
