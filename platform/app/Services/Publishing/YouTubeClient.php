<?php

namespace App\Services\Publishing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class YouTubeClient
{
    public function __construct(private readonly ConnectionStore $connections)
    {
    }

    public function configured(): bool
    {
        return (bool) config('publishing.youtube.client_id')
            && (bool) config('publishing.youtube.client_secret')
            && $this->connections->connected('youtube');
    }

    public function authorizeUrl(string $state): string
    {
        return (string) config('publishing.youtube.oauth_authorize').'?'.http_build_query([
            'client_id' => config('publishing.youtube.client_id'),
            'redirect_uri' => config('publishing.youtube.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('publishing.youtube.scope'),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->timeout(30)->post((string) config('publishing.youtube.oauth_token'), [
            'code' => $code,
            'client_id' => config('publishing.youtube.client_id'),
            'client_secret' => config('publishing.youtube.client_secret'),
            'redirect_uri' => config('publishing.youtube.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new \RuntimeException('YouTube OAuth token exchange failed.');
        }
        return $this->tokenPayload($response->json());
    }

    public function channel(): array
    {
        return $this->request()->get($this->url('channels'), ['part' => 'id,snippet,contentDetails', 'mine' => 'true'])
            ->throw()->json('items.0', []);
    }

    public function uploadVideo(string $path, array $snippet, array $status): array
    {
        if (! is_file($path)) throw new \RuntimeException('YouTube upload source is unavailable.');
        $mime = mime_content_type($path) ?: 'video/mp4';
        $init = $this->request()->withHeaders([
            'X-Upload-Content-Length' => (string) filesize($path),
            'X-Upload-Content-Type' => $mime,
        ])->post($this->uploadUrl('videos').'?uploadType=resumable&part=snippet,status', [
            'snippet' => $snippet,
            'status' => $status,
        ])->throw();
        $location = $init->header('Location');
        if (! is_string($location) || $location === '') throw new \RuntimeException('YouTube did not return a resumable upload URL.');
        $handle = fopen($path, 'rb');
        if (! $handle) throw new \RuntimeException('YouTube upload source cannot be opened.');
        try {
            return $this->request()->withHeaders(['Content-Type' => $mime, 'Content-Length' => (string) filesize($path)])
                ->withOptions(['body' => $handle])->put($location)->throw()->json();
        } finally {
            fclose($handle);
        }
    }

    public function uploadThumbnail(string $videoId, string $path): void
    {
        if (! is_file($path)) return;
        $handle = fopen($path, 'rb');
        if (! $handle) return;
        try {
            $this->request()->withHeaders(['Content-Type' => mime_content_type($path) ?: 'image/jpeg'])
                ->withOptions(['body' => $handle])->post($this->uploadUrl('thumbnails/set').'?uploadType=media&videoId='.rawurlencode($videoId))->throw();
        } finally {
            fclose($handle);
        }
    }

    public function reusableStream(): array
    {
        $credentials = $this->connections->credentials('youtube');
        $stored = $credentials['youtube_stream'] ?? null;
        if (is_array($stored) && ! empty($stored['id']) && ! empty($stored['ingestion_address']) && ! empty($stored['stream_name'])) return $stored;
        $stream = $this->request()->post($this->url('liveStreams').'?part=snippet,cdn,contentDetails,status', [
            'snippet' => ['title' => 'Atapin Media reusable live stream'],
            'cdn' => ['frameRate' => '30fps', 'ingestionType' => 'rtmp', 'resolution' => '1080p'],
        ])->throw()->json();
        $stored = [
            'id' => $stream['id'] ?? null,
            'ingestion_address' => $stream['cdn']['ingestionInfo']['ingestionAddress'] ?? null,
            'stream_name' => $stream['cdn']['ingestionInfo']['streamName'] ?? null,
        ];
        if (in_array(null, $stored, true)) throw new \RuntimeException('YouTube did not return a reusable stream key.');
        $this->connections->saveCredentials('youtube', ['youtube_stream' => $stored]);
        return $stored;
    }

    public function createBroadcast(array $snippet, array $status, array $contentDetails = []): array
    {
        return $this->request()->post($this->url('liveBroadcasts').'?part=snippet,status,contentDetails', [
            'snippet' => $snippet,
            'status' => $status,
            'contentDetails' => $contentDetails + ['enableAutoStart' => true, 'enableAutoStop' => true],
        ])->throw()->json();
    }

    public function bindBroadcast(string $broadcastId, string $streamId): array
    {
        return $this->request()->post($this->url('liveBroadcasts').'/bind?part=id,snippet,contentDetails,status&id='.rawurlencode($broadcastId).'&streamId='.rawurlencode($streamId))->throw()->json();
    }

    public function transitionBroadcast(string $broadcastId, string $status): array
    {
        return $this->request()->post($this->url('liveBroadcasts').'/transition?part=id,snippet,contentDetails,status&broadcastStatus='.rawurlencode($status).'&id='.rawurlencode($broadcastId))->throw()->json();
    }

    /** @return array<int, array<string, mixed>> */
    public function uploadedVideos(): array
    {
        $channel = $this->channel();
        $playlistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        if (! is_string($playlistId)) return [];
        $ids = [];
        $page = null;
        do {
            $query = ['part' => 'contentDetails,snippet', 'playlistId' => $playlistId, 'maxResults' => 50];
            if ($page) $query['pageToken'] = $page;
            $data = $this->request()->get($this->url('playlistItems'), $query)->throw()->json();
            foreach ($data['items'] ?? [] as $item) if (is_string($item['contentDetails']['videoId'] ?? null)) $ids[] = $item['contentDetails']['videoId'];
            $page = $data['nextPageToken'] ?? null;
        } while ($page && count($ids) < 500);
        $videos = [];
        foreach (array_chunk(array_values(array_unique($ids)), 50) as $chunk) {
            $data = $this->request()->get($this->url('videos'), ['part' => 'snippet,status,contentDetails,liveStreamingDetails', 'id' => implode(',', $chunk)])->throw()->json();
            $videos = [...$videos, ...($data['items'] ?? [])];
        }
        return $videos;
    }

    private function tokenPayload(array $payload): array
    {
        $data = ['access_token' => $payload['access_token'], 'token_type' => $payload['token_type'] ?? 'Bearer'];
        if (! empty($payload['refresh_token'])) $data['refresh_token'] = $payload['refresh_token'];
        if (isset($payload['expires_in'])) $data['expires_at'] = now()->addSeconds((int) $payload['expires_in'])->timestamp;
        if (! empty($payload['scope'])) $data['scope'] = $payload['scope'];
        return $data;
    }

    private function request(): PendingRequest
    {
        $credentials = $this->connections->credentials('youtube');
        $expiresAt = (int) ($credentials['expires_at'] ?? 0);
        if ((! is_string($credentials['access_token'] ?? null) || $expiresAt && $expiresAt <= now()->addMinute()->timestamp) && ! empty($credentials['refresh_token'])) {
            $response = Http::asForm()->timeout(30)->post((string) config('publishing.youtube.oauth_token'), [
                'client_id' => config('publishing.youtube.client_id'),
                'client_secret' => config('publishing.youtube.client_secret'),
                'refresh_token' => $credentials['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);
            if (! $response->successful()) throw new \RuntimeException('YouTube OAuth token refresh failed.');
            $refreshed = $this->tokenPayload([...$response->json(), 'refresh_token' => $credentials['refresh_token']]);
            $this->connections->saveCredentials('youtube', $refreshed);
            $credentials = [...$credentials, ...$refreshed];
        }
        if (! is_string($credentials['access_token'] ?? null) || $credentials['access_token'] === '') throw new \RuntimeException('YouTube is not connected.');
        return Http::acceptJson()->withToken($credentials['access_token'])->timeout(120);
    }

    private function url(string $resource): string
    {
        return rtrim((string) config('publishing.youtube.api_base'), '/').'/'.$resource;
    }

    private function uploadUrl(string $resource): string
    {
        return 'https://www.googleapis.com/upload/youtube/v3/'.$resource;
    }
}
