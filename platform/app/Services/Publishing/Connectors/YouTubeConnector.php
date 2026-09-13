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
        if ($record->kind === 'live') return $this->publishLive($record);
        if (! in_array($record->kind, ['video', 'short'], true)) throw new UnsupportedCapability('YouTube community posts are not available through this connector.');
        $asset = app(MediaResolver::class)->video($record);
        if (! $asset) throw new \RuntimeException('No local video file is available for YouTube.');
        $meta = $record->metadata ?? [];
        $status = ['privacyStatus' => $meta['youtube']['privacy_status'] ?? 'public', 'selfDeclaredMadeForKids' => (bool) ($meta['youtube']['made_for_kids'] ?? false)];
        if (! empty($meta['youtube']['publish_at'])) {
            $status['privacyStatus'] = 'private';
            $status['publishAt'] = $meta['youtube']['publish_at'];
        }
        $video = $this->client->uploadVideo($asset['path'], [
            'title' => $record->title,
            'description' => (string) ($record->body ?? ''),
            'tags' => array_values(array_filter($meta['tags'] ?? $meta['original_tags'] ?? [], 'is_string')),
            'categoryId' => (string) ($meta['youtube']['category_id'] ?? '22'),
        ], $status);
        $id = $video['id'] ?? null;
        if (! is_string($id) || $id === '') throw new \RuntimeException('YouTube upload returned no video ID.');
        if ($image = app(MediaResolver::class)->image($record)) $this->client->uploadThumbnail($id, $image['path']);
        return ['external_id' => $id, 'external_url' => 'https://www.youtube.com/watch?v='.rawurlencode($id), 'remote_status' => $status['privacyStatus'], 'payload' => ['video_id' => $id]];
    }

    private function publishLive(\App\Models\SourceRecord $record): array
    {
        $stream = $this->client->reusableStream();
        $meta = $record->metadata ?? [];
        $broadcast = $this->client->createBroadcast([
            'title' => $record->title,
            'description' => (string) ($record->body ?? ''),
            'scheduledStartTime' => $meta['starts_at'] ?? now()->toIso8601String(),
        ], ['privacyStatus' => $meta['youtube']['privacy_status'] ?? 'public'], [
            'enableAutoStart' => true,
            'enableAutoStop' => true,
        ]);
        $id = $broadcast['id'] ?? null;
        if (! is_string($id) || $id === '') throw new \RuntimeException('YouTube broadcast creation returned no ID.');
        $this->client->bindBroadcast($id, (string) $stream['id']);
        if ($image = app(MediaResolver::class)->image($record)) $this->client->uploadThumbnail($id, $image['path']);
        return ['external_id' => $id, 'external_url' => 'https://www.youtube.com/watch?v='.rawurlencode($id), 'remote_status' => 'scheduled', 'payload' => ['broadcast_id' => $id, 'stream_id' => $stream['id']]];
    }
}
