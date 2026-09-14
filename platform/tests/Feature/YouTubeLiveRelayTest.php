<?php

namespace Tests\Feature;

use App\Models\{Publication, SourceRecord};
use App\Services\{PublicBroadcast, Settings};
use App\Services\Publishing\{ConnectionStore, YouTubeLiveRelay};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class YouTubeLiveRelayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        app(Settings::class)->update(['social_connections' => ['youtube' => ['external_id' => 'channel']]]);
        app(ConnectionStore::class)->saveCredentials('youtube', ['access_token' => 'token', 'youtube_stream' => [
            'id' => 'one-stream', 'ingestion_address' => 'rtmp://a.rtmp.youtube.com/live2', 'stream_name' => 'secret-key',
        ]]);
    }

    public function test_relay_input_uses_existing_authorized_hls_without_exposing_key_in_output(): void
    {
        $relay = new class(app(ConnectionStore::class)) extends YouTubeLiveRelay {
            public function command(): Process { return $this->process('live'); }
        };
        $process = $relay->command();
        $command = $process->getCommandLine();
        $this->assertStringContainsString('http://127.0.0.1:8888/live/index.m3u8', $command);
        $this->assertStringNotContainsString('rtmp://127.0.0.1:1935', $command);
        $this->assertStringContainsString('rtmp://a.rtmp.youtube.com/live2/secret-key', $command);
        $this->assertTrue($process->isOutputDisabled());
        $this->assertNull($process->getTimeout());
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'auth', 'kind' => 'video', 'title' => 'Live', 'status' => 'ready',
            'metadata' => ['public_section' => 'live', 'public_published' => true, 'live_stream_enabled' => true, 'live_ingest_active' => true, 'live_status' => 'live']]);
        $this->assertTrue(app(PublicBroadcast::class)->authorize(['path' => 'live', 'action' => 'read', 'protocol' => 'hls']));
        $this->assertFalse(app(PublicBroadcast::class)->authorize(['path' => 'live', 'action' => 'read', 'protocol' => 'rtmp']));
    }

    public function test_foreground_hook_retains_process_until_live_ends_and_stops_it(): void
    {
        $record = SourceRecord::create(['source' => 'website', 'source_id' => 'relay', 'kind' => 'video', 'title' => 'Live', 'status' => 'ready',
            'metadata' => ['public_section' => 'live', 'public_published' => true, 'live_stream_enabled' => true, 'live_ingest_active' => true, 'live_status' => 'live']]);
        $publication = Publication::updateOrCreate(['source_record_id' => $record->id, 'provider' => 'youtube', 'direction' => 'outbound'], [
            'status' => 'published', 'payload' => ['broadcast_id' => 'broadcast']]);
        $process = \Mockery::mock(Process::class);
        $process->shouldReceive('start')->once()->andReturnUsing(function () use ($record) {
            app(PublicBroadcast::class)->signal('live', false);
        });
        $process->shouldReceive('isRunning')->once()->andReturnTrue();
        $process->shouldReceive('stop')->once()->with(2)->andReturn(0);
        $relay = new class(app(ConnectionStore::class), $process) extends YouTubeLiveRelay {
            public function __construct(ConnectionStore $connections, private readonly Process $testProcess) { parent::__construct($connections); }
            protected function process(string $path): Process { return $this->testProcess; }
        };
        $relay->run('live');
        $this->assertSame('ended', $record->fresh()->metadata['live_status']);
        $this->assertSame('relaying', $publication->fresh()->remote_status);
        $this->assertNotNull($publication->fresh()->payload['broadcast_id']);
    }
}
