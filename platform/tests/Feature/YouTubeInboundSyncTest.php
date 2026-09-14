<?php

namespace Tests\Feature;

use App\Models\{ExternalItem, Publication, SourceRecord};
use App\Services\Settings;
use App\Services\Publishing\{ConnectionStore, MediaResolver, YouTubeClient, YouTubeInboundSync};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\TestCase;

class YouTubeInboundSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('media-canonical');
        app(Settings::class)->update(['social_connections' => ['youtube' => ['external_id' => 'channel']]]);
        app(ConnectionStore::class)->saveCredentials('youtube', ['access_token' => 'token']);
    }

    private function sync(array $video): YouTubeInboundSync
    {
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('configured')->andReturnTrue();
        $client->shouldReceive('uploadedVideos')->andReturn([$video]);
        $client->shouldReceive('videosByIds')->andReturn([]);
        return app(YouTubeInboundSync::class);
    }

    public function test_own_uploaded_video_is_linked_to_original_without_duplicate_or_download(): void
    {
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'ours', 'kind' => 'video', 'title' => 'Ours', 'status' => 'ready', 'metadata' => ['public_published' => true]]);
        Publication::updateOrCreate(['source_record_id' => $record->id, 'provider' => 'youtube', 'direction' => 'outbound'], [
            'status' => 'processing', 'external_id' => 'youtube-id', 'payload' => ['video_id' => 'youtube-id']]);
        $this->mock(MediaResolver::class)->shouldNotReceive('video');
        $sync = $this->sync(['id' => 'youtube-id', 'snippet' => ['title' => 'Remote title']]);
        $sync->run();
        $sync->run();
        $this->assertSame(1, SourceRecord::count());
        $this->assertSame(1, ExternalItem::count());
        $this->assertSame($record->id, ExternalItem::first()->source_record_id);
        $this->assertSame('Ours', $record->fresh()->title);
        $this->assertTrue($record->fresh()->metadata['public_published']);
    }

    public function test_external_video_waits_for_review_and_enabling_website_does_not_reupload_youtube(): void
    {
        $this->mock(MediaResolver::class)->shouldReceive('video')->andReturn(['path' => 'existing-canonical.mp4']);
        $sync = $this->sync(['id' => 'external-id', 'snippet' => ['title' => 'External video', 'publishedAt' => '2026-09-01T10:00:00Z'], 'status' => ['privacyStatus' => 'public']]);
        $sync->run();
        $record = SourceRecord::firstOrFail();
        $this->assertSame('review', $record->status);
        $this->assertFalse($record->metadata['public_published']);
        $this->assertTrue($record->metadata['external_sync_pending_review']);
        $record->update(['status' => 'ready']);
        $record->update(['metadata' => [...$record->metadata, 'public_published' => true]]);
        $this->assertSame('published', Publication::where('provider', 'youtube')->first()->status);
        $this->assertSame('external-id', Publication::where('provider', 'youtube')->first()->external_id);
        Queue::assertNotPushed(\App\Jobs\PublishToPlatform::class);
        $sync->run();
        $this->assertSame(1, SourceRecord::count());
        $this->assertSame('ready', $record->fresh()->status);
        $this->assertTrue($record->fresh()->metadata['public_published']);
    }

    public function test_previous_failed_import_is_revisited_not_mistaken_for_own_upload(): void
    {
        $record = SourceRecord::create(['source' => 'youtube', 'source_id' => 'external-id', 'kind' => 'video', 'title' => 'External', 'status' => 'review', 'metadata' => []]);
        Publication::create(['source_record_id' => $record->id, 'provider' => 'youtube', 'direction' => 'outbound', 'status' => 'published',
            'external_id' => 'external-id', 'payload' => ['origin' => 'youtube_sync']]);
        ExternalItem::create(['source_record_id' => $record->id, 'provider' => 'youtube', 'external_id' => 'external-id', 'kind' => 'video', 'status' => 'failed', 'error' => 'Old failure']);
        $this->mock(MediaResolver::class)->shouldReceive('video')->once()->andReturn(['path' => 'canonical.mp4']);
        $this->sync(['id' => 'external-id'])->run();
        $this->assertSame('imported', ExternalItem::first()->status);
        $this->assertNull(ExternalItem::first()->error);
    }

    public function test_active_remote_live_is_review_only_and_waits_for_recording_before_download(): void
    {
        $this->mock(MediaResolver::class)->shouldNotReceive('video');
        $this->sync(['id' => 'remote-live', 'snippet' => ['title' => 'Live', 'liveBroadcastContent' => 'live'],
            'liveStreamingDetails' => ['actualStartTime' => '2026-09-01T10:00:00Z']])->run();
        $this->assertSame('review', SourceRecord::first()->status);
        $this->assertFalse(SourceRecord::first()->metadata['public_published']);
        $this->assertSame('discovered', ExternalItem::first()->status);
    }

    public function test_old_pending_import_is_retried_even_outside_daily_discovery_window(): void
    {
        $record = SourceRecord::create(['source' => 'youtube', 'source_id' => 'old-pending', 'kind' => 'video', 'title' => 'Old pending', 'status' => 'review', 'metadata' => ['public_published' => false]]);
        ExternalItem::create(['source_record_id' => $record->id, 'provider' => 'youtube', 'external_id' => 'old-pending', 'kind' => 'video', 'status' => 'failed']);
        $client = $this->mock(YouTubeClient::class);
        $client->shouldReceive('configured')->andReturnTrue();
        $client->shouldReceive('uploadedVideos')->once()->andReturn([]);
        $client->shouldReceive('videosByIds')->once()->with(['old-pending'])->andReturn([['id' => 'old-pending', 'snippet' => ['publishedAt' => '2020-01-01T12:00:00Z']]]);
        $this->mock(MediaResolver::class)->shouldReceive('video')->andReturn(['path' => 'canonical.mp4']);
        app(YouTubeInboundSync::class)->run();
        $this->assertSame('imported', ExternalItem::first()->status);
        $this->assertSame('review', $record->fresh()->status);
        $this->assertFalse($record->fresh()->metadata['public_published']);
    }
}
