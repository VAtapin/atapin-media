<?php

namespace App\Services\Publishing;

use App\Models\{Publication, SourceRecord};
use App\Services\PublicBroadcast;
use Symfony\Component\Process\Process;

class YouTubeLiveRelay
{
    public function __construct(private readonly YouTubeClient $youtube, private readonly ConnectionStore $connections)
    {
    }

    public function reconcile(): void
    {
        $active = SourceRecord::query()
            ->where('metadata->public_section', 'live')
            ->where('metadata->live_stream_enabled', true)
            ->where('metadata->live_ingest_active', true)
            ->where('metadata->live_status', 'live')
            ->get();

        foreach ($active as $record) $this->start($record);

        foreach ($this->stateFiles() as $file) {
            $state = $this->readState($file);
            $recordId = (int) ($state['record_id'] ?? 0);
            if (! $recordId || $active->contains('id', $recordId)) continue;
            $this->stop($state);
        }
    }

    public function start(SourceRecord $record): void
    {
        $publication = Publication::query()
            ->where('source_record_id', $record->id)
            ->where('provider', 'youtube')
            ->where('direction', 'outbound')
            ->where('status', 'published')
            ->latest('id')->first();
        $payload = $publication?->payload ?? [];
        $broadcastId = $payload['broadcast_id'] ?? $publication?->external_id;
        if (! is_string($broadcastId) || $broadcastId === '') return;

        $existing = $this->readState($this->statePath($record->id));
        if ($this->isAlive((int) ($existing['pid'] ?? 0))) return;
        if ($existing) @unlink($this->statePath($record->id));

        $stream = $this->connections->credentials('youtube')['youtube_stream'] ?? [];
        $address = $stream['ingestion_address'] ?? null;
        $key = $stream['stream_name'] ?? null;
        if (! is_string($address) || ! is_string($key) || $address === '' || $key === '') return;

        $local = 'rtmp://127.0.0.1:1935/'.PublicBroadcast::SHARED_PATH;
        $remote = rtrim($address, '/').'/'.$key;
        $process = new Process([
            (string) config('publishing.live_relay_ffmpeg', 'ffmpeg'),
            '-nostdin', '-hide_banner', '-loglevel', 'warning',
            '-i', $local, '-c', 'copy', '-f', 'flv', $remote,
        ]);
        $process->setTimeout(null);
        $process->start();
        $pid = $process->getPid();
        if (! is_int($pid) || $pid <= 0) throw new \RuntimeException('The YouTube Live relay did not start.');
        $this->writeState($record->id, ['record_id' => $record->id, 'pid' => $pid, 'broadcast_id' => $broadcastId, 'started_at' => now()->toIso8601String()]);
    }

    private function stop(array $state): void
    {
        $pid = (int) ($state['pid'] ?? 0);
        if ($pid > 0 && $this->isAlive($pid)) {
            $kill = new Process(['kill', '-TERM', (string) $pid]);
            $kill->setTimeout(5);
            $kill->run();
        }
        $broadcastId = $state['broadcast_id'] ?? null;
        if (is_string($broadcastId) && $broadcastId !== '' && $this->youtube->configured()) {
            try { $this->youtube->transitionBroadcast($broadcastId, 'complete'); } catch (\Throwable) { /* retry on the next lifecycle action */ }
        }
        $recordId = (int) ($state['record_id'] ?? 0);
        if ($recordId) {
            Publication::query()->where('source_record_id', $recordId)->where('provider', 'youtube')->where('direction', 'outbound')->whereNotNull('id')->update(['remote_status' => 'ended']);
        }
        $path = $recordId ? $this->statePath($recordId) : null;
        if ($path && is_file($path)) @unlink($path);
    }

    private function isAlive(int $pid): bool
    {
        if ($pid <= 0) return false;
        if (is_dir('/proc/'.$pid)) return true;
        $process = new Process(['kill', '-0', (string) $pid]);
        $process->setTimeout(2);
        $process->run();
        return $process->isSuccessful();
    }

    private function statePath(int $recordId): string
    {
        return dirname(base_path(), 2).'/private/atapin-live/relays/'.$recordId.'.json';
    }

    /** @return array<int, string> */
    private function stateFiles(): array
    {
        $root = dirname(base_path(), 2).'/private/atapin-live/relays';
        return is_dir($root) ? (glob($root.'/*.json') ?: []) : [];
    }

    private function readState(string $path): array
    {
        if (! is_file($path)) return [];
        $value = json_decode((string) file_get_contents($path), true);
        return is_array($value) ? $value : [];
    }

    private function writeState(int $recordId, array $state): void
    {
        $path = $this->statePath($recordId);
        $root = dirname($path);
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) throw new \RuntimeException('The private Live relay directory is unavailable.');
        if (file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES), LOCK_EX) === false) throw new \RuntimeException('The Live relay state cannot be saved.');
        @chmod($path, 0600);
    }
}
