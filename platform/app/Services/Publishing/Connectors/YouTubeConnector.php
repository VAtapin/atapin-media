<?php

namespace App\Services\Publishing\Connectors;

use App\Contracts\PublishingConnector;
use App\Models\Publication;
use App\Services\Publishing\{ConnectionStore, MediaResolver, UnsupportedCapability, YouTubeClient};

class YouTubeConnector implements PublishingConnector
{
    public function __construct(private readonly YouTubeClient $client, private readonly ConnectionStore $connections)
    {
    }

    public function provider(): string
    {
        return 'youtube';
    }

    public function capabilities(): array
    {
        return ['video' => true, 'short' => true, 'post' => false, 'live' => true, 'inbound_video' => true, 'inbound_live' => true];
    }

    public function publish(Publication $publication): array
    {
        $record = $publication->record;
        if (! $record) throw new \RuntimeException('Publication content no longer exists.');
        if (! $this->connections->connected('youtube')) throw new UnsupportedCapability('YouTube is disconnected.');
        if ($record->publishingKind() === 'live') return $this->publishLive($publication);
        if (! in_array($record->kind, ['video', 'short'], true)) throw new UnsupportedCapability('YouTube community posts are not available through this connector.');
        $meta = $record->metadata ?? [];
        $status = ['privacyStatus' => 'public', 'selfDeclaredMadeForKids' => (bool) ($meta['youtube']['made_for_kids'] ?? false)];
        $id = $publication->external_id;
        if (! $id) {
            $asset = app(MediaResolver::class)->video($record);
            if (! $asset) throw new \RuntimeException('No local video file is available for YouTube.');
            $video = $this->client->uploadVideo($asset['path'], [
                'title' => $record->title,
                'description' => (string) ($record->body ?? ''),
                'tags' => array_values(array_filter($meta['tags'] ?? $meta['original_tags'] ?? [], 'is_string')),
                'categoryId' => (string) ($meta['youtube']['category_id'] ?? '22'),
            ], $status);
            $id = $video['id'] ?? null;
            if (! is_string($id) || $id === '') throw new \RuntimeException('YouTube upload returned no video ID.');
            $publication->update(['external_id' => $id, 'external_url' => 'https://www.youtube.com/watch?v='.rawurlencode($id),
                'payload' => [...($publication->payload ?? []), 'video_id' => $id]]);
        } else {
            $video = $this->client->video($id);
        }
        if (($video['status']['privacyStatus'] ?? null) !== 'public') {
            throw new \RuntimeException('YouTube did not make the video public. Check API project verification and channel restrictions; the uploaded video ID has been preserved.');
        }
        if ($image = app(MediaResolver::class)->image($record)) $this->client->uploadThumbnail($id, $image['path']);
        return ['external_id' => $id, 'external_url' => 'https://www.youtube.com/watch?v='.rawurlencode($id), 'remote_status' => $status['privacyStatus'], 'payload' => ['video_id' => $id]];
    }

    private function publishLive(Publication $publication): array
    {
        $record = $publication->record;
        $meta = $record->metadata ?? [];
        $id = $publication->payload['broadcast_id'] ?? null;
        if (($meta['live_status'] ?? null) === 'ended' || ($publication->payload['live_end_requested'] ?? false)) {
            if (! $id) throw new UnsupportedCapability('The Live event has already ended.');
            return ['external_id' => $id, 'remote_status' => $this->client->completeBroadcast($id)];
        }
        $stream = $this->client->reusableStream();
        if (! $id) {
            $broadcast = $this->client->createBroadcast([
                'title' => $record->title,
                'description' => (string) ($record->body ?? ''),
                'scheduledStartTime' => ! empty($meta['starts_at']) ? \Illuminate\Support\Carbon::parse($meta['starts_at'])->toIso8601String() : now()->toIso8601String(),
            ], ['privacyStatus' => 'public'], [
                'enableAutoStart' => true,
                'enableAutoStop' => true,
            ]);
            $id = $broadcast['id'] ?? null;
            if (! is_string($id) || $id === '') throw new \RuntimeException('YouTube broadcast creation returned no ID.');
            $publication->update(['external_id' => $id, 'external_url' => 'https://www.youtube.com/watch?v='.rawurlencode($id),
                'payload' => [...($publication->payload ?? []), 'broadcast_id' => $id, 'stream_id' => $stream['id']]]);
        }
        if (! ($publication->payload['broadcast_bound'] ?? false)) {
            $this->client->bindBroadcast($id, (string) $stream['id']);
            $publication->update(['payload' => [...($publication->payload ?? []), 'broadcast_bound' => true]]);
        }
        if ($image = app(MediaResolver::class)->image($record)) $this->client->uploadThumbnail($id, $image['path']);
        if (($record->fresh()->metadata['live_status'] ?? null) === 'ended') {
            return ['external_id' => $id, 'remote_status' => $this->client->completeBroadcast($id)];
        }
        return ['external_id' => $id, 'external_url' => 'https://www.youtube.com/watch?v='.rawurlencode($id), 'remote_status' => 'scheduled', 'payload' => ['broadcast_id' => $id, 'stream_id' => $stream['id']]];
    }
}
