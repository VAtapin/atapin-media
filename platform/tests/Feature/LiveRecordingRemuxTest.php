<?php

namespace Tests\Feature;

use App\Jobs\ProcessLiveRecording;
use App\Models\{Media, SourceRecord};
use App\Services\PublicBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Process, Queue, Storage};
use Tests\TestCase;

class LiveRecordingRemuxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media-canonical');
        Queue::fake();
        config(['platform.media_upload_reserve_free_bytes' => 0]);
    }

    private function event(): SourceRecord
    {
        return SourceRecord::create(['source'=>'website','source_id'=>'live-remux','kind'=>'video','title'=>'Live remux','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true]]);
    }

    public function test_completed_live_recording_is_remuxed_before_public_storage(): void
    {
        Storage::fake('live-recordings');
        $event=$this->event();$path='live-'.$event->id;
        Storage::disk('live-recordings')->put($path.'/segment.mp4','fmp4 source');
        $file=Storage::disk('live-recordings')->path($path.'/segment.mp4');
        Process::fake(function($process){file_put_contents(end($process->command),'seekable mp4');return Process::result();});

        app(PublicBroadcast::class)->recording($path,$file);

        $media=Media::firstOrFail();
        $this->assertSame('remuxed',$media->metadata['live_remux_status']);
        $this->assertSame('seekable mp4',Storage::disk('media-canonical')->get($media->path));
        $this->assertFileDoesNotExist($file);
        Process::assertRan(fn($process)=>in_array('-c',$process->command,true)&&in_array('copy',$process->command,true)&&in_array('+faststart',$process->command,true));
    }

    public function test_failed_remux_keeps_the_original_recording_available(): void
    {
        Storage::fake('live-recordings');
        $event=$this->event();$path='live-'.$event->id;
        Storage::disk('live-recordings')->put($path.'/segment.mp4','original fallback');
        $file=Storage::disk('live-recordings')->path($path.'/segment.mp4');
        Process::fake(['*'=>Process::result(exitCode:1)]);

        app(PublicBroadcast::class)->recording($path,$file);

        $media=Media::firstOrFail();
        $this->assertSame('fallback',$media->metadata['live_remux_status']);
        $this->assertSame('original fallback',Storage::disk('media-canonical')->get($media->path));
        $this->assertFileDoesNotExist($file);
    }

    public function test_recording_hook_queues_processing_and_deduplicates_the_segment(): void
    {
        Queue::fake();
        app(PublicBroadcast::class)->queueRecording('live-42','/private/recording.mp4');
        Queue::assertPushed(ProcessLiveRecording::class,fn($job)=>$job->path==='live-42'&&$job->file==='/private/recording.mp4');
    }
}
