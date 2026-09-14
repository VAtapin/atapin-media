<?php

namespace App\Services\Publishing;

use App\Models\{Publication, SourceRecord};
use App\Services\PublicBroadcast;
use Symfony\Component\Process\Process;

class YouTubeLiveRelay
{
    public function __construct(private readonly ConnectionStore $connections)
    {
    }

    // MediaMTX owns the foreground hook and interrupts it when the publisher leaves.
    // Retain the Process for the entire stream; do not detach it or store PIDs.
    public function run(string $path): void
    {
        $record = app(PublicBroadcast::class)->record($path);
        if (! $record || ! $this->connections->connected('youtube')) return;
        if (isset($record->metadata['publishing_targets']) && ! in_array('youtube', $record->metadata['publishing_targets'], true)) return;
        $lock = fopen(storage_path('framework/cache/youtube-live-relay.lock'), 'c');
        if ($lock === false) throw new \RuntimeException('The Live relay lock is unavailable.');
        if (! flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return; }
        $process = null;
        $stopping = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, static function () use (&$stopping) { $stopping = true; });
            pcntl_signal(SIGTERM, static function () use (&$stopping) { $stopping = true; });
        }
        try {
            while (! $stopping && $this->active($record) && $this->connections->connected('youtube')) {
                $publication = Publication::where('source_record_id', $record->id)->where('provider', 'youtube')
                    ->where('direction', 'outbound')->first();
                if (! $process && $publication?->status === 'published' && ! empty($publication->payload['broadcast_id'])
                    && ! ($publication->payload['live_end_requested'] ?? false)) {
                    try {
                        $process = $this->process($path);
                        $process->start();
                        $publication->update(['remote_status' => 'relaying', 'error' => null]);
                    } catch (\Throwable $error) {
                        $process = null;
                        $publication->update(['remote_status' => 'relay_failed', 'error' => $this->connections->safeError($error)]);
                        sleep(5);
                    }
                }
                if ($process && ! $process->isRunning()) {
                    $process = null;
                    $publication?->update(['remote_status' => 'relay_failed', 'error' => 'The YouTube relay stopped; reconnecting.']);
                    sleep(5);
                }
                sleep(1);
            }
        } finally {
            $process?->stop(2);
            flock($lock, LOCK_UN);
            fclose($lock); // The OS also releases this lock if MediaMTX restarts a crashed hook.
            if (($record->fresh()->metadata['live_status'] ?? null) === 'ended') {
                app(PublishingService::class)->queueLiveCompletion($record);
            }
        }
    }

    protected function process(string $path): Process
    {
        $stream = $this->connections->credentials('youtube')['youtube_stream'] ?? [];
        $address = $stream['ingestion_address'] ?? null;
        $key = $stream['stream_name'] ?? null;
        if (! is_string($address) || $address === '' || ! is_string($key) || $key === '') {
            throw new \RuntimeException('The reusable YouTube stream is unavailable.');
        }
        $process = new Process([
            (string) config('publishing.live_relay_ffmpeg', 'ffmpeg'),
            '-nostdin', '-hide_banner', '-loglevel', 'error', '-rw_timeout', '15000000',
            '-i', 'http://127.0.0.1:8888/'.rawurlencode($path).'/index.m3u8',
            '-c', 'copy', '-f', 'flv', rtrim($address, '/').'/'.$key,
        ]);
        $process->setTimeout(null);
        $process->disableOutput(); // ffmpeg diagnostics may contain the secret ingestion URL.
        return $process;
    }

    private function active(SourceRecord $record): bool
    {
        $record->refresh();
        return ! $record->trashed() && $record->status === 'ready'
            && ($record->metadata['public_published'] ?? false)
            && ($record->metadata['live_stream_enabled'] ?? false)
            && ($record->metadata['live_status'] ?? null) === 'live';
    }
}
