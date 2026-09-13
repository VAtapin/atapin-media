<?php

namespace App\Services\Publishing\Connectors;

use App\Contracts\PublishingConnector;
use App\Models\Publication;
use App\Services\Publishing\{ConnectionStore, MediaResolver, UnsupportedCapability};
use Illuminate\Support\Facades\Http;

class MetaConnector implements PublishingConnector
{
    public function __construct(private readonly string $provider, private readonly ConnectionStore $connections)
    {
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function capabilities(): array
    {
        return $this->provider === 'facebook'
            ? ['video' => true, 'short' => true, 'post' => true, 'live' => false]
            : ['video' => true, 'short' => true, 'post' => true, 'live' => false];
    }

    public function publish(Publication $publication): array
    {
        $record = $publication->record;
        $credentials = $this->connections->credentials($this->provider);
        $account = $this->connections->connection($this->provider)['external_id'] ?? null;
        $token = $credentials['access_token'] ?? $credentials['api_key'] ?? null;
        if (! is_string($account) || $account === '' || ! is_string($token) || $token === '') throw new \RuntimeException(ucfirst($this->provider).' connection is incomplete.');
        if ($record->kind === 'live') throw new UnsupportedCapability(ucfirst($this->provider).' Live output is not configured.');
        return $this->provider === 'facebook' ? $this->facebook($record, $account, $token) : $this->instagram($record, $account, $token);
    }

    private function facebook(\App\Models\SourceRecord $record, string $page, string $token): array
    {
        $base = 'https://graph.facebook.com/'.config('publishing.meta.api_version');
        $asset = app(MediaResolver::class)->video($record);
        if ($asset && in_array($record->kind, ['video', 'short'], true)) {
            $response = Http::timeout(300)->withToken($token)->attach('source', fopen($asset['path'], 'rb'), basename($asset['path']))
                ->post($base.'/'.$page.'/videos', ['description' => $this->caption($record), 'title' => $record->title]);
        } elseif ($image = app(MediaResolver::class)->image($record)) {
            $response = Http::timeout(120)->withToken($token)->attach('source', fopen($image['path'], 'rb'), basename($image['path']))
                ->post($base.'/'.$page.'/photos', ['caption' => $this->caption($record), 'published' => 'true']);
        } else {
            $response = Http::timeout(60)->withToken($token)->post($base.'/'.$page.'/feed', ['message' => $this->caption($record)]);
        }
        $response->throw();
        $id = $response->json('id') ?? $response->json('post_id');
        if (! is_string($id)) throw new \RuntimeException('Facebook returned no publication ID.');
        return ['external_id' => $id, 'external_url' => 'https://www.facebook.com/'.rawurlencode($id), 'remote_status' => 'published'];
    }

    private function instagram(\App\Models\SourceRecord $record, string $account, string $token): array
    {
        $image = app(MediaResolver::class)->image($record);
        $video = app(MediaResolver::class)->video($record);
        $media = $image['media'] ?? $video['media'] ?? null;
        $url = $media?->publicUrl();
        if (! $media || ! is_string($url)) throw new UnsupportedCapability('Instagram requires a public image or video asset.');
        $base = 'https://graph.facebook.com/'.config('publishing.meta.api_version');
        $payload = ['caption' => $this->caption($record), 'access_token' => $token];
        if ($video) { $payload['media_type'] = 'REELS'; $payload['video_url'] = $url; }
        else { $payload['image_url'] = $url; }
        $container = Http::timeout(120)->post($base.'/'.$account.'/media', $payload)->throw()->json('id');
        if (! is_string($container)) throw new \RuntimeException('Instagram returned no media container ID.');
        $id = Http::timeout(120)->post($base.'/'.$account.'/media_publish', ['creation_id' => $container, 'access_token' => $token])->throw()->json('id');
        if (! is_string($id)) throw new \RuntimeException('Instagram returned no publication ID.');
        return ['external_id' => $id, 'external_url' => 'https://www.instagram.com/p/'.rawurlencode($id), 'remote_status' => 'published', 'payload' => ['container_id' => $container]];
    }

    private function caption(\App\Models\SourceRecord $record): string
    {
        return trim($record->title."\n\n".(string) ($record->body ?? ''));
    }
}
