<?php

namespace Tests\Feature;

use App\Models\{Media, Publication, SourceRecord};
use App\Services\{PublicBroadcast, Settings};
use App\Services\Publishing\{ConnectionStore, LiveRelay, PublishingService, VideoRenderer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue, Storage};
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PublishingMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        Storage::fake('media-canonical');
        Http::preventStrayRequests();
    }

    private function media(string $kind): Media
    {
        $extension = ['image' => 'jpg', 'video' => 'mp4', 'audio' => 'mp3'][$kind];
        $path = $kind.'.'.$extension;
        Storage::disk('media-canonical')->put($path, $kind.'-bytes');
        return Media::create(['source' => 'upload', 'source_id' => $kind, 'title' => $kind, 'kind' => $kind, 'disk' => 'media-canonical', 'path' => $path,
            'original_name' => $path, 'mime' => ['image' => 'image/jpeg', 'video' => 'video/mp4', 'audio' => 'audio/mpeg'][$kind], 'bytes' => strlen($kind.'-bytes')]);
    }

    public function test_rendered_video_uses_canonical_storage_is_reused_and_keeps_original_record_unchanged(): void
    {
        $image = $this->media('image');
        $audio = $this->media('audio');
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'post', 'kind' => 'post', 'title' => 'Title', 'status' => 'ready', 'metadata' => ['media_ids' => [$image->id, $audio->id]]]);
        $process = \Mockery::mock(Process::class);
        $process->shouldReceive('setTimeout')->once()->with(3500);
        $process->shouldReceive('disableOutput')->once();
        $process->shouldReceive('isSuccessful')->once()->andReturnTrue();
        $renderer = new class($process) extends VideoRenderer {
            public array $command = [];
            public string $root = '';
            public function __construct(private readonly Process $fake) {}
            protected function process(array $command, string $root): Process { $this->command = $command; $this->root = $root; return $this->fake; }
        };
        // Subprocess mocked: this tests orchestration/storage, not actual ffmpeg encoding.
        $process->shouldReceive('run')->once()->andReturnUsing(fn () => file_put_contents($renderer->root.'/video.mp4', 'rendered-video'));
        $first = $renderer->video($record);
        $second = $renderer->video($record);
        $this->assertSame($first['media']->id, $second['media']->id);
        $this->assertSame(hash('sha256', 'rendered-video').'.mp4', $first['media']->path);
        $this->assertContains('-shortest', $renderer->command);
        $this->assertContains('-loop', $renderer->command);
        $this->assertSame([$image->id, $audio->id], $record->fresh()->metadata['media_ids']);
        $this->assertFileDoesNotExist($renderer->root.'/video.mp4');
    }

    public function test_existing_video_is_not_rendered_again(): void
    {
        $video = $this->media('video');
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'video', 'kind' => 'post', 'title' => 'Title', 'status' => 'ready', 'metadata' => ['media_ids' => [$video->id]]]);
        $this->assertSame($video->id, app(VideoRenderer::class)->video($record)['media']->id);
    }

    public function test_text_renderer_passes_user_text_as_a_file_not_filter_code_and_cleans_up(): void
    {
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'text', 'kind' => 'post', 'title' => "Text %{expr:1} ' : ;", 'body' => '<b>Body</b>', 'status' => 'ready', 'metadata' => []]);
        $process = \Mockery::mock(Process::class);
        $process->shouldReceive('setTimeout')->once();
        $process->shouldReceive('disableOutput')->once();
        $process->shouldReceive('isSuccessful')->once()->andReturnFalse();
        $renderer = new class($process) extends VideoRenderer {
            public array $command = []; public string $root = '';
            public function __construct(private readonly Process $fake) {}
            protected function process(array $command, string $root): Process { $this->command = $command; $this->root = $root; return $this->fake; }
        };
        $process->shouldReceive('run')->once()->andReturnUsing(function () use ($renderer) {
            $this->assertStringContainsString('%{expr:1}', file_get_contents($renderer->root.'/caption.txt'));
            $this->assertStringNotContainsString('%{expr:1}', implode(' ', $renderer->command));
            $this->assertStringContainsString('expansion=none', implode(' ', $renderer->command));
            return 1;
        });
        try { $renderer->video($record); $this->fail('Expected failed adaptation.'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('Video adaptation failed', $error->getMessage()); }
        $this->assertFileDoesNotExist($renderer->root.'/caption.txt');
        $this->assertSame(0, Media::count());
    }

    public function test_one_failed_rtmp_output_does_not_block_another_and_both_stop_with_event(): void
    {
        $connections = app(ConnectionStore::class);
        $connections->saveLiveOutput('rtmp_a', 'A', 'rtmp://a.example/live/secret-a');
        $connections->saveLiveOutput('rtmp_b', 'B', 'rtmps://b.example/live/secret-b');
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'live', 'kind' => 'video', 'title' => 'Live', 'status' => 'ready',
            'metadata' => ['public_section' => 'live', 'public_published' => true, 'live_stream_enabled' => true, 'live_ingest_active' => true, 'live_status' => 'live', 'publishing_targets' => ['website', 'rtmp_a', 'rtmp_b']]]);
        Publication::whereIn('provider', ['rtmp_a', 'rtmp_b'])->update(['status' => 'published']);
        $bad = \Mockery::mock(Process::class);
        $bad->shouldReceive('start')->once()->andThrow(new \RuntimeException('Cannot start rtmp://a.example/live/secret-a'));
        $good = \Mockery::mock(Process::class);
        $good->shouldReceive('start')->once()->andReturnUsing(fn () => app(PublicBroadcast::class)->signal('live', false));
        $good->shouldReceive('isRunning')->once()->andReturnTrue();
        $good->shouldReceive('stop')->once()->with(2)->andReturn(0);
        $relay = new class($connections, $bad, $good) extends LiveRelay {
            public function __construct(ConnectionStore $connections, private readonly Process $bad, private readonly Process $good) { parent::__construct($connections); }
            protected function outputProcess(string $path, string $url): Process { return str_contains($url, 'a.example') ? $this->bad : $this->good; }
        };
        $relay->run('live');
        $this->assertSame('ended', $record->fresh()->metadata['live_status']);
        $this->assertSame('ended', Publication::where('provider', 'rtmp_b')->first()->remote_status);
        $this->assertStringNotContainsString('secret-a', Publication::where('provider', 'rtmp_a')->first()->error);
    }

    public function test_youtube_live_is_reported_live_only_after_public_api_confirmation(): void
    {
        app(Settings::class)->update(['social_connections' => ['youtube' => ['external_id' => 'channel']]]);
        $connections = app(ConnectionStore::class);
        $connections->saveCredentials('youtube', ['access_token' => 'token']);
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'live', 'kind' => 'video', 'title' => 'Live', 'status' => 'ready',
            'metadata' => ['public_section' => 'live', 'public_published' => true, 'live_stream_enabled' => true, 'live_ingest_active' => true, 'live_status' => 'live', 'publishing_targets' => ['website', 'youtube']]]);
        $publication = Publication::where('provider', 'youtube')->firstOrFail();
        $publication->update(['status' => 'published', 'payload' => [...$publication->payload, 'broadcast_id' => 'broadcast']]);
        Http::fake(['https://www.googleapis.com/youtube/v3/liveBroadcasts*' => function () {
            app(PublicBroadcast::class)->signal('live', false);
            return Http::response(['items' => [['status' => ['privacyStatus' => 'public', 'lifeCycleStatus' => 'live']]]]);
        }]);
        $process = \Mockery::mock(Process::class);
        $process->shouldReceive('start')->once();
        $process->shouldReceive('isRunning')->once()->andReturnTrue();
        $process->shouldReceive('stop')->once()->with(2)->andReturn(0);
        $relay = new class($connections, $process) extends LiveRelay {
            public function __construct(ConnectionStore $connections, private readonly Process $fake) { parent::__construct($connections); }
            protected function process(string $path): Process { return $this->fake; }
        };
        $relay->run('live');
        $this->assertSame('live', $publication->fresh()->remote_status);
        Http::assertSentCount(1);
    }

    public function test_facebook_video_waits_for_processing_without_uploading_again(): void
    {
        app(Settings::class)->update(['social_connections' => ['facebook' => ['external_id' => 'page']]]);
        app(ConnectionStore::class)->saveCredentials('facebook', ['access_token' => 'token']);
        $video = $this->media('video');
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'fb', 'kind' => 'post', 'title' => 'Post video', 'status' => 'ready', 'metadata' => ['media_ids' => [$video->id]]]);
        $publication = Publication::create(['source_record_id' => $record->id, 'provider' => 'facebook', 'status' => 'queued', 'direction' => 'outbound']);
        Http::fake([
            'https://graph.facebook.com/*/page/videos' => Http::response(['id' => 'video-id']),
            'https://graph.facebook.com/*/video-id?*' => Http::sequence()->push(['status' => ['video_status' => 'processing']])->push(['status' => ['video_status' => 'ready'], 'published' => true, 'permalink_url' => 'https://facebook.com/actual-video']),
        ]);
        $service = app(PublishingService::class);
        $service->execute($publication->id);
        $this->assertSame('queued', $publication->fresh()->status);
        $this->travel(31)->seconds();
        $service->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertSame('https://facebook.com/actual-video', $publication->fresh()->external_url);
        Http::assertSentCount(3);
    }

    public function test_facebook_unpublication_is_confirmed_without_deletion(): void
    {
        app(Settings::class)->update(['social_connections' => ['facebook' => ['external_id' => 'page']]]);
        app(ConnectionStore::class)->saveCredentials('facebook', ['access_token' => 'token']);
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'fb-hide', 'kind' => 'video', 'title' => 'Video', 'status' => 'ready', 'metadata' => ['public_published' => false]]);
        $publication = Publication::create(['source_record_id' => $record->id, 'provider' => 'facebook', 'status' => 'queued', 'external_id' => 'video-id', 'payload' => ['action' => 'hide', 'facebook_type' => 'video']]);
        Http::fake(['https://graph.facebook.com/*/video-id' => Http::response(['success' => true]), 'https://graph.facebook.com/*/video-id?*' => Http::response(['published' => false])]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('hidden', $publication->fresh()->remote_status);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['published'] === false);
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_telegram_audio_uses_native_upload_and_updates_existing_caption(): void
    {
        app(Settings::class)->update(['social_connections' => ['telegram' => ['external_id' => '@channel']]]);
        app(ConnectionStore::class)->saveCredentials('telegram', ['api_key' => 'bot-token']);
        $audio = $this->media('audio');
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'tg', 'kind' => 'post', 'title' => 'Audio', 'status' => 'ready', 'metadata' => ['media_ids' => [$audio->id]]]);
        $publication = Publication::create(['source_record_id' => $record->id, 'provider' => 'telegram', 'direction' => 'outbound', 'status' => 'queued']);
        Http::fake([
            'https://api.telegram.org/botbot-token/getChat' => Http::response(['result' => ['type' => 'channel', 'username' => 'channel']]),
            'https://api.telegram.org/botbot-token/sendAudio' => Http::response(['ok' => true, 'result' => ['message_id' => 42]]),
            'https://api.telegram.org/botbot-token/editMessageCaption' => Http::response(['ok' => true]),
        ]);
        $service = app(PublishingService::class);
        $service->execute($publication->id);
        $this->assertSame('42', $publication->fresh()->external_id);
        $publication->refresh()->update(['status' => 'queued', 'payload' => [...$publication->payload, 'action' => 'update']]);
        $service->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/editMessageCaption') && $request['message_id'] === '42');
        Http::assertSentCount(3);
    }

    public function test_live_title_update_does_not_interrupt_the_running_relay(): void
    {
        app(Settings::class)->update(['social_connections' => ['youtube' => ['external_id' => 'channel']]]);
        $connections = app(ConnectionStore::class);
        $connections->saveCredentials('youtube', ['access_token' => 'token']);
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'live-edit', 'kind' => 'video', 'title' => 'Live', 'status' => 'ready',
            'metadata' => ['public_section' => 'live', 'public_published' => true, 'live_stream_enabled' => true, 'live_ingest_active' => true, 'live_status' => 'live', 'publishing_targets' => ['website', 'youtube']]]);
        $publication = Publication::where('provider', 'youtube')->firstOrFail();
        $publication->update(['status' => 'published', 'external_id' => 'broadcast', 'payload' => [...$publication->payload, 'broadcast_id' => 'broadcast', 'applied_signature' => app(PublishingService::class)->signature($record)]]);
        $this->mock(\App\Services\Publishing\YouTubeClient::class)->shouldReceive('broadcastStatus')->once()->andReturn('live');
        $process = \Mockery::mock(Process::class);
        $process->shouldReceive('start')->once()->andReturnUsing(fn () => $record->update(['title' => 'Changed live title']));
        $process->shouldReceive('isRunning')->once()->andReturnTrue();
        $process->shouldReceive('isRunning')->once()->andReturnUsing(function () use ($publication) {
            $this->assertSame('queued', $publication->fresh()->status);
            app(PublicBroadcast::class)->signal('live', false);
            return true;
        });
        $process->shouldReceive('stop')->once()->with(2)->andReturnUsing(function () use ($record) {
            $this->assertSame('ended', $record->fresh()->metadata['live_status']);
            return 0;
        });
        $relay = new class($connections, $process) extends LiveRelay {
            public function __construct(ConnectionStore $connections, private readonly Process $fake) { parent::__construct($connections); }
            protected function process(string $path): Process { return $this->fake; }
        };
        $relay->run('live');
        $this->assertSame('update', $publication->fresh()->payload['action']);
    }

    public function test_telegram_does_not_treat_an_api_error_as_confirmed_deletion(): void
    {
        app(Settings::class)->update(['social_connections' => ['telegram' => ['external_id' => '@channel']]]);
        app(ConnectionStore::class)->saveCredentials('telegram', ['api_key' => 'bot-token']);
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'tg-error', 'kind' => 'post', 'title' => 'Text', 'status' => 'ready', 'metadata' => []]);
        $publication = Publication::create(['source_record_id' => $record->id, 'provider' => 'telegram', 'status' => 'queued', 'external_id' => '42', 'payload' => ['action' => 'delete']]);
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Forbidden'])]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('failed', $publication->fresh()->status);
        $this->assertNotSame('deleted', $publication->fresh()->remote_status);
    }

    public function test_success_resets_the_failure_budget_for_later_operations(): void
    {
        app(Settings::class)->update(['social_connections' => ['telegram' => ['external_id' => '@channel']]]);
        app(ConnectionStore::class)->saveCredentials('telegram', ['api_key' => 'bot-token']);
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'tg-reset', 'kind' => 'post', 'title' => 'Text', 'status' => 'ready', 'metadata' => []]);
        $publication = Publication::create(['source_record_id' => $record->id, 'provider' => 'telegram', 'status' => 'queued', 'external_id' => '42', 'payload' => ['action' => 'update', 'consecutive_failures' => 4]]);
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);
        app(PublishingService::class)->execute($publication->id);
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertSame(0, $publication->fresh()->payload['consecutive_failures']);
    }
}
