<?php

namespace App\Services\Publishing\Connectors;

use App\Contracts\{ManagesPublications, PublishingConnector};
use App\Models\Publication;
use App\Services\Publishing\{ConnectionStore, MediaResolver, PublicationPending, UnsupportedCapability, VideoRenderer, YouTubeClient};

class YouTubeConnector implements PublishingConnector, ManagesPublications
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
        return ['video' => true, 'short' => true, 'post' => true, 'live' => true, 'inbound_video' => true, 'inbound_live' => true];
    }

    public function publish(Publication $publication): array
    {
        $record = $publication->record;
        if (! $record) throw new \RuntimeException('Publication content no longer exists.');
        if (! $this->connections->connected('youtube')) throw new UnsupportedCapability('YouTube is disconnected.');
        if ($record->publishingKind() === 'live') return $this->publishLive($publication);
        if (! in_array($record->kind, ['video', 'short', 'post'], true)) throw new UnsupportedCapability('This content cannot be published to YouTube.');
        $meta = $record->metadata ?? [];
        $status = ['privacyStatus' => 'public', 'selfDeclaredMadeForKids' => (bool) ($meta['youtube']['made_for_kids'] ?? false)];
        $id = $publication->external_id;
        if (! $id) {
            $asset = app(MediaResolver::class)->video($record);
            if (! $asset && ($record->kind === 'post' || ($meta['public_section'] ?? null) === 'podcast' || app(MediaResolver::class)->audio($record))) $asset = app(VideoRenderer::class)->video($record);
            if (! $asset) throw new \RuntimeException('No local video file is available for YouTube.');
            $video = $this->client->uploadVideo($asset['path'], [
                'title' => $record->title,
                'description' => (string) ($record->body ?? ''),
                'tags' => array_values(array_filter($meta['tags'] ?? $meta['original_tags'] ?? [], 'is_string')),
                'categoryId' => (string) ($meta['youtube']['category_id'] ?? '22'),
            ], $status, $publication);
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
        $processing = $video['processingDetails']['processingStatus'] ?? null;
        if (in_array($processing, ['failed', 'terminated'], true) || in_array($video['status']['uploadStatus'] ?? null, ['failed', 'rejected', 'deleted'], true)) throw new \RuntimeException('YouTube rejected or failed to process the uploaded video.');
        if ($processing === 'processing' || ($video['status']['uploadStatus'] ?? null) === 'uploaded') throw new PublicationPending();
        if ($image = app(MediaResolver::class)->image($record)) $this->client->uploadThumbnail($id, $image['path']);
        return ['external_id' => $id, 'external_url' => 'https://www.youtube.com/watch?v='.rawurlencode($id), 'remote_status' => $status['privacyStatus'], 'payload' => ['video_id' => $id]];
    }

    public function manage(Publication $publication, string $action): array
    {
        $id = $publication->external_id;
        if ($action === 'delete') {
            $this->client->deleteVideo($id);
            return ['remote_status' => 'deleted'];
        }
        $record = $publication->record;
        $meta = $record->metadata ?? [];
        $this->client->updateVideo($id, [
            'title' => $record->title, 'description' => (string) ($record->body ?? ''),
            'tags' => array_values(array_filter($meta['tags'] ?? $meta['original_tags'] ?? [], 'is_string')),
            'categoryId' => (string) ($meta['youtube']['category_id'] ?? '22'),
        ], $action === 'hide' ? 'private' : 'public');
        if ($action !== 'hide' && ($image = app(MediaResolver::class)->image($record))) $this->client->uploadThumbnail($id, $image['path']);
        $video = $this->client->video($id);
        $expected = $action === 'hide' ? 'private' : 'public';
        if (($video['status']['privacyStatus'] ?? null) !== $expected) throw new \RuntimeException('YouTube did not apply the requested visibility.');
        return ['remote_status' => $action === 'hide' ? 'hidden' : 'public'];
    }

    public function actions(): array { return ['update', 'hide', 'delete']; }

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
            if (isset($broadcast['status']['privacyStatus']) && $broadcast['status']['privacyStatus'] !== 'public') throw new \RuntimeException('YouTube did not create a public Live broadcast; its ID has been retained.');
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
