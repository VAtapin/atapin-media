<?php

namespace App\Services\Publishing;

use App\Models\{Publication, SourceRecord};
use App\Services\PublicBroadcast;
use Symfony\Component\Process\Process;

class LiveRelay
{
    public function __construct(private readonly ConnectionStore $connections)
    {
    }

    // MediaMTX owns the foreground hook and interrupts it when the publisher leaves.
    // Retain the Process for the entire stream; do not detach it or store PIDs.
    public function run(string $path): void
    {
        $record = app(PublicBroadcast::class)->record($path);
        if (! $record) return;
        $lock = fopen(storage_path('framework/cache/youtube-live-relay.lock'), 'c');
        if ($lock === false) throw new \RuntimeException('The Live relay lock is unavailable.');
        if (! flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return; }
        $processes = [];
        $retryAt = [];
        $lastCheck = 0;
        $stopping = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, static function () use (&$stopping) { $stopping = true; });
            pcntl_signal(SIGTERM, static function () use (&$stopping) { $stopping = true; });
        }
        try {
            while (! $stopping && $this->active($record)) {
                $publications = Publication::where('source_record_id', $record->id)->where('direction', 'outbound')
                    ->where(fn ($query) => $query->where('provider', 'youtube')->orWhere('provider', 'like', 'rtmp_%'))->get()->keyBy('provider');
                foreach ($processes as $provider => $process) {
                    if (! $this->connections->connected($provider) || ! isset($publications[$provider]) || ! $this->selected($record, $provider)
                        || in_array($publications[$provider]->payload['action'] ?? null, ['hide', 'delete'], true)) {
                        $process->stop(2);
                        unset($processes[$provider]);
                        $publications->get($provider)?->update(['remote_status' => 'cancelled']);
                    }
                }
                foreach ($publications as $provider => $publication) {
                    if (! $this->connections->connected($provider) || ! $this->selected($record, $provider) || ($publication->payload['live_end_requested'] ?? false)) continue;
                    if ($publication->status !== 'published' && ($publication->payload['action'] ?? null) !== 'update') continue;
                    if (in_array($publication->remote_status, ['deleted', 'ended'], true) || in_array($publication->payload['action'] ?? null, ['hide', 'delete'], true)) continue;
                    if ($provider === 'youtube' && empty($publication->payload['broadcast_id'])) continue;
                    if (isset($processes[$provider]) && ! $processes[$provider]->isRunning()) {
                        unset($processes[$provider]);
                        $retryAt[$provider] = time() + 5;
                        $publication->update(['remote_status' => 'relay_failed', 'error' => 'The Live relay stopped; reconnecting.']);
                    }
                    if (isset($processes[$provider]) || time() < ($retryAt[$provider] ?? 0)) continue;
                    try {
                        $process = $provider === 'youtube' ? $this->process($path) : $this->outputProcess($path, $this->connections->credentials($provider)['url']);
                        $process->start();
                        $processes[$provider] = $process;
                        $publication->update(['remote_status' => 'relaying', 'error' => null]);
                        // Also catch a process that exits immediately after start.
                        if (! $process->isRunning()) {
                            unset($processes[$provider]);
                            $retryAt[$provider] = time() + 5;
                            $publication->update(['remote_status' => 'relay_failed', 'error' => 'The Live relay stopped; reconnecting.']);
                        }
                    } catch (\Throwable $error) {
                        if (isset($processes[$provider])) $processes[$provider]->stop(2);
                        unset($processes[$provider]);
                        $retryAt[$provider] = time() + 5;
                        $publication->update(['remote_status' => 'relay_failed', 'error' => $this->connections->safeError($error)]);
                    }
                }
                if (time() - $lastCheck >= 15 && isset($processes['youtube']) && $this->active($record)) {
                    $lastCheck = time();
                    try {
                        $publication = $publications['youtube'];
                        $status = app(YouTubeClient::class)->broadcastStatus($publication->payload['broadcast_id']);
                        if ($status === 'live') $publication->update(['remote_status' => 'live', 'error' => null]);
                        elseif ($status === 'complete') {
                            $processes['youtube']->stop(2);
                            unset($processes['youtube']);
                            $publication->update(['remote_status' => 'ended', 'payload' => [...$publication->payload, 'live_end_requested' => true]]);
                        }
                    } catch (\Throwable $error) {
                        $publications['youtube']->update(['error' => $this->connections->safeError($error)]);
                    }
                }
                sleep(1);
            }
        } finally {
            foreach ($processes as $process) $process->stop(2);
            flock($lock, LOCK_UN);
            fclose($lock); // The OS also releases this lock if MediaMTX restarts a crashed hook.
            if (($record->fresh()->metadata['live_status'] ?? null) === 'ended') {
                app(PublishingService::class)->queueLiveCompletion($record);
                Publication::where('source_record_id', $record->id)->where('provider', 'like', 'rtmp_%')->update(['remote_status' => 'ended']);
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
        return $this->outputProcess($path, rtrim($address, '/').'/'.$key);
    }

    protected function outputProcess(string $path, string $url): Process
    {
        $process = new Process([
            (string) config('publishing.live_relay_ffmpeg', 'ffmpeg'),
            '-nostdin', '-hide_banner', '-loglevel', 'error', '-rw_timeout', '15000000',
            '-i', 'http://127.0.0.1:8888/'.rawurlencode($path).'/index.m3u8',
            '-c', 'copy', '-f', 'flv', $url,
        ]);
        $process->setTimeout(null);
        $process->disableOutput(); // ffmpeg diagnostics may contain the secret ingestion URL.
        return $process;
    }

    private function selected(SourceRecord $record, string $provider): bool
    {
        return ! isset($record->metadata['publishing_targets']) || in_array($provider, $record->metadata['publishing_targets'], true);
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
