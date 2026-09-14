<?php

namespace App\Services\Publishing\Connectors;

use App\Contracts\{ManagesPublications, PublishingConnector};
use App\Models\Publication;
use App\Services\Publishing\{MediaResolver, PublicationPending, UnsupportedCapability, VideoRenderer, XClient};

class XConnector implements PublishingConnector, ManagesPublications
{
    public function __construct(private readonly XClient $client) {}
    public function provider(): string { return 'x'; }
    public function capabilities(): array { return ['video' => true, 'short' => true, 'post' => true, 'live' => false]; }
    public function actions(): array { return ['delete']; }
    public function manage(Publication $publication, string $action): array
    {
        if ($action !== 'delete') throw new UnsupportedCapability('X post updates and private visibility are not supported by this connector.');
        $response = $this->client->request()->delete($this->client->url('tweets/'.$publication->external_id))->throw();
        if ($response->json('data.deleted') !== true) throw new \RuntimeException('X did not confirm deletion.');
        return ['remote_status' => 'deleted'];
    }

    public function publish(Publication $publication): array
    {
        $record = $publication->record;
        if ($record->publishingKind() === 'live') throw new UnsupportedCapability('Use a configured RTMP destination for Live output.');
        if ($publication->external_id) {
            $this->client->request()->get($this->client->url('tweets/'.$publication->external_id))->throw();
            return $this->result($publication->external_id);
        }
        $user = $this->client->request()->get($this->client->url('users/me'), ['user.fields' => 'protected'])->throw()->json('data', []);
        if (($user['protected'] ?? true) !== false) throw new \RuntimeException('X requires a public account for public distribution.');
        $resolver = app(MediaResolver::class);
        $asset = $resolver->video($record);
        if (! $asset && $resolver->audio($record)) $asset = app(VideoRenderer::class)->video($record);
        $asset ??= $resolver->image($record);
        $data = ['text' => mb_strimwidth(\App\Services\Publishing\PlatformText::caption($record,'x'), 0, 280, '…')];
        if ($asset) $data['media'] = ['media_ids' => [$this->upload($publication, $asset)]];
        $id = $this->client->request()->post($this->client->url('tweets'), $data)->throw()->json('data.id');
        if (! is_string($id)) throw new \RuntimeException('X returned no post ID.');
        $publication->update(['external_id' => $id]);
        $this->client->request()->get($this->client->url('tweets/'.$id))->throw();
        return $this->result($id);
    }

    private function upload(Publication $publication, array $asset): string
    {
        $payload = $publication->payload ?? [];
        $size = filesize($asset['path']);
        $id = $payload['x_media_id'] ?? null;
        if (! $id) {
            $data = $this->client->request()->post($this->client->url('media/upload/initialize'), [
                'total_bytes' => $size, 'media_type' => $asset['media']->mime, 'media_category' => $asset['media']->kind === 'video' ? 'tweet_video' : 'tweet_image',
            ])->throw()->json('data', []);
            $id = $data['id'] ?? null;
            if (! is_string($id)) throw new \RuntimeException('X returned no media ID.');
            $payload = [...$payload, 'x_media_id' => $id, 'x_segment' => 0, 'x_bytes' => 0];
            $publication->update(['payload' => $payload]);
        }
        if (! ($payload['x_finalized'] ?? false)) {
            $handle = fopen($asset['path'], 'rb');
            if (! $handle) throw new \RuntimeException('The X media source cannot be opened.');
            try {
                fseek($handle, $payload['x_bytes']);
                while (! feof($handle)) {
                    $chunk = fread($handle, 1024 * 1024);
                    if ($chunk === false) throw new \RuntimeException('The X media source cannot be read.');
                    if ($chunk === '') break;
                    $this->client->request()->attach('media', $chunk, 'chunk')->post($this->client->url('media/upload/'.$id.'/append'), ['segment_index' => $payload['x_segment']])->throw();
                    $payload['x_segment']++;
                    $payload['x_bytes'] += strlen($chunk);
                    $publication->update(['payload' => $payload, 'remote_status' => 'uploading']);
                }
            } finally { fclose($handle); }
            $status = $this->client->request()->post($this->client->url('media/upload/'.$id.'/finalize'))->throw()->json('data.processing_info', []);
            $payload['x_finalized'] = true;
            $publication->update(['payload' => $payload]);
        } else {
            $status = $this->client->request()->get($this->client->url('media/upload'), ['media_id' => $id, 'command' => 'STATUS'])->throw()->json('data.processing_info', []);
        }
        if (($status['state'] ?? null) === 'failed') throw new \RuntimeException('X media processing failed.');
        if (in_array($status['state'] ?? null, ['pending', 'in_progress'], true)) throw new PublicationPending();
        return $id;
    }

    private function result(string $id): array { return ['external_id' => $id, 'external_url' => 'https://x.com/i/status/'.rawurlencode($id), 'remote_status' => 'published']; }
}
