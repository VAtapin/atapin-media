<?php

namespace App\Services\Publishing;

use App\Models\{ExternalItem, Publication, SourceRecord};
use App\Services\CanonicalMediaStorage;
use App\Services\Importing\ImportedMediaRegistry;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class YouTubeInboundSync
{
    public function __construct(private readonly YouTubeClient $client, private readonly MediaResolver $media)
    {
    }

    public function run(): int
    {
        if (! $this->client->configured()) return 0;
        $count = 0;
        $pending = ExternalItem::where('provider', 'youtube')->whereIn('status', ['failed', 'discovered'])->pluck('external_id')->all();
        foreach ($this->client->uploadedVideos() as $video) {
            $id = $video['id'] ?? null;
            if (! is_string($id) || $id === '') continue;
            $count++;
            $this->import($video);
        }
        if ($pending) foreach ($this->client->videosByIds($pending) as $video) $this->import($video);
        return $count;
    }

    private function import(array $video): void
    {
        $id = (string) $video['id'];
        $snippet = $video['snippet'] ?? [];
        $remoteDate = $snippet['publishedAt'] ?? null;
        $item = ExternalItem::firstOrNew(['provider' => 'youtube', 'external_id' => $id]);
        $item->kind = 'video';
        $item->remote_published_at = $remoteDate;
        $item->payload = $video;
        $outbound = Publication::where('provider', 'youtube')->where('direction', 'outbound')->where('external_id', $id)->first();
        // A video sent by this installation belongs to the original record, not a second review copy.
        if ($outbound && ! $outbound->record) return;
        $record = $outbound?->record ?? SourceRecord::firstOrCreate(['source' => 'youtube', 'source_id' => $id], [
            'kind' => 'video',
            'title' => $snippet['title'] ?? $id,
            'body' => $snippet['description'] ?? '',
            'status' => 'review',
            'metadata' => ['public_published' => false, 'external_sync_pending_review' => true, 'youtube_api' => $video, 'youtube_id' => $id, 'published_at' => $remoteDate],
        ]);
        $item->source_record_id = $record->id;
        if (! $outbound) {
            Publication::firstOrCreate(['source_record_id' => $record->id, 'provider' => 'youtube', 'direction' => 'outbound'], [
                'status' => 'published', 'external_id' => $id, 'external_url' => 'https://www.youtube.com/watch?v='.rawurlencode($id),
                'remote_status' => $video['status']['privacyStatus'] ?? 'public', 'published_at' => $remoteDate ?? now(),
                'payload' => ['video_id' => $id, 'origin' => 'youtube_sync'],
            ]);
        }
        $sentByUs = $outbound && ($outbound->payload['origin'] ?? null) !== 'youtube_sync';
        if ($sentByUs || (($snippet['liveBroadcastContent'] ?? 'none') !== 'none' && empty($video['liveStreamingDetails']['actualEndTime']))) {
            $item->status = $sentByUs ? 'imported' : 'discovered';
            $item->error = null;
            $item->save();
            return;
        }
        if (! ($record->metadata['external_sync_pending_review'] ?? true)) $item->status = 'imported';
        if (! $this->media->video($record)) {
            try {
                $this->download($record, $id);
                $record->refresh();
                $item->status = 'imported';
                $item->imported_at = now();
                $item->error = null;
            } catch (\Throwable $error) {
                $item->status = 'failed';
                $item->error = app(ConnectionStore::class)->safeError($error);
            }
        } else {
            $item->status = 'imported';
            $item->imported_at ??= now();
            $item->error = null;
        }
        $item->save();
    }

    private function download(SourceRecord $record, string $id): void
    {
        $root = Storage::disk('local')->path('youtube-sync/'.$id);
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) throw new \RuntimeException('YouTube sync storage is unavailable.');
        $url = 'https://www.youtube.com/watch?v='.rawurlencode($id);
        $process = new Process([config('publishing.youtube.downloader'), '--no-playlist', '--no-progress', '--output', $root.'/'.$id.'.%(ext)s', $url]);
        $process->setTimeout(3600);
        try {
            $process->run();
            if (! $process->isSuccessful()) throw new \RuntimeException('YouTube media download failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
            $files = glob($root.'/'.$id.'.*') ?: [];
            $path = collect($files)->first(fn ($file) => is_file($file) && ! str_ends_with($file, '.part') && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['mp4','webm','mkv','mov'], true));
            if (! is_string($path)) throw new \RuntimeException('YouTube download produced no video file.');
            $mime = mime_content_type($path) ?: 'video/mp4';
            $stored = app(CanonicalMediaStorage::class)->storePath($path, $mime, pathinfo($path, PATHINFO_EXTENSION));
            $media = app(ImportedMediaRegistry::class)->register([
                'source' => 'youtube', 'source_id' => $id.':api', 'disk' => $stored['disk'], 'path' => $stored['path'],
                'sha256' => $stored['sha256'], 'kind' => 'video', 'mime' => $mime, 'bytes' => $stored['bytes'],
                'title' => $record->title, 'original_name' => $stored['filename'], 'status' => 'ready', 'metadata' => ['youtube_id' => $id, 'origin' => 'youtube_api'],
            ]);
            $metadata = $record->metadata ?? [];
            $metadata['media_ids'] = array_values(array_unique([...($metadata['media_ids'] ?? []), $media->id]));
            $record->update(['metadata' => $metadata]);
            $media->usages()->firstOrCreate(['subject_type' => SourceRecord::class, 'subject_id' => (string) $record->id, 'used_as' => 'video']);
        } finally {
            Storage::disk('local')->deleteDirectory('youtube-sync/'.$id);
        }
    }
}
