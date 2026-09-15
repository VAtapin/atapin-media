<?php

namespace App\Services\Publishing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use App\Models\Publication;
use App\Services\Settings;

class YouTubeClient
{
    public function __construct(
        private readonly ConnectionStore $connections,
        private readonly Settings $settings,
    )
    {
    }

    public function configured(): bool
    {
        return app(OAuthAppCredentials::class)->configured('youtube')
            && $this->connections->connected('youtube');
    }

    public function oauthConfigured(): bool
    {
        return app(OAuthAppCredentials::class)->configured('youtube');
    }

    public function authorizeUrl(string $state): string
    {
        $oauth = app(OAuthAppCredentials::class)->get('youtube');
        return (string) config('publishing.youtube.oauth_authorize').'?'.http_build_query([
            'client_id' => $oauth['client_id'],
            'redirect_uri' => route('desktop.publishing.youtube.callback'),
            'response_type' => 'code',
            'scope' => config('publishing.youtube.scope'),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $oauth = app(OAuthAppCredentials::class)->get('youtube');
        $response = Http::asForm()->timeout(30)->post((string) config('publishing.youtube.oauth_token'), [
            'code' => $code,
            'client_id' => $oauth['client_id'],
            'client_secret' => $oauth['client_secret'],
            'redirect_uri' => route('desktop.publishing.youtube.callback'),
            'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new \RuntimeException('YouTube OAuth token exchange failed.');
        }
        return $this->tokenPayload($response->json());
    }

    public function channel(?array $credentials = null): array
    {
        return $this->request($credentials)->timeout(30)->get($this->url('channels'), ['part' => 'id,snippet,contentDetails', 'mine' => 'true'])
            ->throw()->json('items.0', []);
    }

    public function check(): array
    {
        $connection = $this->connections->connection('youtube');
        $channelId = $connection['external_id'] ?? null;
        if (! is_string($channelId) || $channelId === '' || ! empty($connection['revoked_at'])) {
            throw new YouTubeConnectionExpired('YouTube is not connected.');
        }
        if (! $this->oauthConfigured()) {
            throw new YouTubeConnectionCheckFailed('configuration', 'YouTube OAuth application credentials are missing.');
        }

        $api = $this->request()->timeout(30);
        $credentials = $this->connections->credentials('youtube');
        $accessToken = $credentials['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') throw new YouTubeConnectionExpired('YouTube access token is missing.');

        $tokenInfo = Http::acceptJson()->timeout(30)->get((string) config('publishing.youtube.oauth_token_info'), [
            'access_token' => $accessToken,
        ]);
        if (in_array($tokenInfo->status(), [400, 401], true)) throw new YouTubeConnectionExpired('YouTube access token expired.');
        if ($tokenInfo->status() === 403) throw new YouTubeConnectionCheckFailed('permissions', 'YouTube token permissions cannot be inspected.');
        if (! $tokenInfo->successful()) throw new \RuntimeException('YouTube token inspection failed (HTTP '.$tokenInfo->status().').');
        if (is_numeric($tokenInfo->json('expires_in')) && (int) $tokenInfo->json('expires_in') <= 0) {
            throw new YouTubeConnectionExpired('YouTube access token expired.');
        }

        $oauth = app(OAuthAppCredentials::class)->get('youtube');
        $audience = $tokenInfo->json('aud');
        if (! is_string($audience) || ! hash_equals((string) $oauth['client_id'], $audience)) {
            throw new YouTubeConnectionCheckFailed('configuration', 'YouTube token belongs to another OAuth application.');
        }
        $granted = preg_split('/\s+/', trim((string) $tokenInfo->json('scope', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $required = preg_split('/\s+/', trim((string) config('publishing.youtube.scope')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($required === [] || array_diff($required, $granted)) {
            throw new YouTubeConnectionCheckFailed('permissions', 'YouTube publishing permission is missing.');
        }

        $channelResponse = $api->get($this->url('channels'), ['part' => 'id,snippet', 'mine' => 'true']);
        if ($channelResponse->status() === 401) throw new YouTubeConnectionExpired('YouTube access token expired.');
        if ($channelResponse->status() === 403) throw new YouTubeConnectionCheckFailed('permissions', 'YouTube channel permission is missing.');
        if (! $channelResponse->successful()) throw new \RuntimeException('YouTube channel check failed (HTTP '.$channelResponse->status().').');
        $channel = $channelResponse->json('items.0', []);
        if (! is_array($channel) || ! is_string($channel['id'] ?? null) || $channel['id'] === '') {
            throw new YouTubeConnectionCheckFailed('channel_access', 'No YouTube channel is accessible.');
        }
        if (! hash_equals($channelId, $channel['id'])) {
            throw new YouTubeConnectionCheckFailed('channel_identity', 'The authorized YouTube channel does not match the saved channel.');
        }

        $this->markHealthy($channel);

        return [
            'id' => $channelId,
            'name' => $channel['snippet']['title'] ?? $connection['display_name'] ?? $channelId,
            'publishing_scope' => true,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function markError(string $reason = 'error'): void
    {
        $social = $this->settings->get('social_connections', []);
        if (! is_array($social['youtube'] ?? null)) return;
        $social['youtube']['last_error_at'] = now()->toIso8601String();
        $social['youtube']['last_error_reason'] = $reason;
        $this->settings->update(['social_connections' => $social]);
    }

    public function markExpired(): void
    {
        $social = $this->settings->get('social_connections', []);
        if (! is_array($social['youtube'] ?? null)) return;
        $social['youtube']['revoked_at'] = now()->toIso8601String();
        unset($social['youtube']['last_error_at'], $social['youtube']['last_error_reason']);
        $this->settings->update(['social_connections' => $social]);
    }

    public function uploadVideo(string $path, array $snippet, array $status, Publication $publication): array
    {
        if (! is_file($path)) throw new \RuntimeException('YouTube upload source is unavailable.');
        $mime = mime_content_type($path) ?: 'video/mp4';
        return $this->resumeUpload($publication, $path, $mime, $snippet, $status);
    }

    private function resumeUpload(Publication $publication, string $path, string $mime, array $snippet, array $status): array
    {
        $size = filesize($path);
        $session = $publication->payload['upload_session'] ?? null;
        if ($session) {
            $session = json_decode(Crypt::decryptString($session), true, 512, JSON_THROW_ON_ERROR);
            if ($session['size'] !== $size || $session['path'] !== $path) throw new \RuntimeException('The upload source changed; the existing session cannot be reused.');
            $url = $session['url'];
            try {
                $response = $this->request()->timeout(60)->withHeaders(['Content-Range' => 'bytes */'.$size, 'Content-Length' => '0'])->withBody('', $mime)->put($url);
            } catch (\Throwable) { throw new \RuntimeException('YouTube upload status is unavailable; the encrypted session has been retained.'); }
        } else {
            $init = $this->request()->timeout(60)->withHeaders(['X-Upload-Content-Length' => (string) $size, 'X-Upload-Content-Type' => $mime])
                ->post($this->uploadUrl('videos').'?uploadType=resumable&part=snippet,status', ['snippet' => $snippet, 'status' => $status])->throw();
            $url = $init->header('Location');
            // Never send a bearer token to an arbitrary Location supplied by a remote response.
            if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'www.googleapis.com') throw new \RuntimeException('YouTube returned an invalid upload session.');
            $publication->update(['payload' => [...($publication->payload ?? []), 'upload_session' => Crypt::encryptString(json_encode(['url' => $url, 'path' => $path, 'size' => $size], JSON_THROW_ON_ERROR))]]);
            $response = null;
        }
        $handle = fopen($path, 'rb');
        if (! $handle) throw new \RuntimeException('The upload source cannot be opened.');
        try {
            $offset = 0;
            $lastOffset = -1;
            while (true) {
                if ($response) {
                    if ($response->successful() && is_string($response->json('id'))) {
                        $publication->update(['payload' => [...($publication->payload ?? []), 'uploaded_bytes' => $size, 'total_bytes' => $size]]);
                        return $response->json();
                    }
                    if ($response->status() !== 308) throw new \RuntimeException('YouTube upload session failed (HTTP '.$response->status().'); its checkpoint has been retained.');
                    $range = $response->header('Range');
                    $offset = $range && preg_match('/^bytes=0-(\d+)$/', $range, $match) ? (int) $match[1] + 1 : 0;
                }
                if ($offset >= $size) throw new \RuntimeException('YouTube accepted the bytes but has not returned a video ID. Retry the retained session.');
                if ($offset <= $lastOffset) throw new \RuntimeException('YouTube upload made no progress; retry the retained session.');
                $lastOffset = $offset;
                fseek($handle, $offset);
                $chunk = fread($handle, min(8 * 1024 * 1024, $size - $offset));
                if ($chunk === false || $chunk === '') throw new \RuntimeException('The upload source cannot be read.');
                $end = $offset + strlen($chunk) - 1;
                $publication->update(['remote_status' => 'uploading', 'payload' => [...($publication->payload ?? []), 'uploaded_bytes' => $offset, 'total_bytes' => $size]]);
                try {
                    $response = $this->request()->timeout(120)->withHeaders(['Content-Range' => 'bytes '.$offset.'-'.$end.'/'.$size, 'Content-Length' => (string) strlen($chunk)])
                        ->withBody($chunk, $mime)->put($url);
                } catch (\Throwable) {
                    throw new \RuntimeException('YouTube upload was interrupted; retry will query and resume the retained session.');
                }
            }
        } finally { fclose($handle); }
    }

    public function uploadThumbnail(string $videoId, string $path): void
    {
        if (! is_file($path)) return;
        $handle = fopen($path, 'rb');
        if (! $handle) return;
        try {
            $this->request()->withHeaders(['Content-Type' => mime_content_type($path) ?: 'image/jpeg'])
                ->withBody($handle, mime_content_type($path) ?: 'image/jpeg')->post($this->uploadUrl('thumbnails/set').'?uploadType=media&videoId='.rawurlencode($videoId))->throw();
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
            'contentDetails' => ['isReusable' => true],
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

    public function video(string $id): array
    {
        return $this->request()->get($this->url('videos'), ['part' => 'snippet,status,processingDetails', 'id' => $id])->throw()->json('items.0', []);
    }

    public function updateVideo(string $id, array $snippet, string $privacy): void
    {
        $this->request()->put($this->url('videos').'?part=snippet,status', ['id' => $id, 'snippet' => $snippet, 'status' => ['privacyStatus' => $privacy]])->throw();
    }

    public function deleteVideo(string $id): void
    {
        $this->request()->delete($this->url('videos').'?id='.rawurlencode($id))->throw();
    }

    public function broadcastStatus(string $id): string
    {
        $status = $this->request()->timeout(10)->get($this->url('liveBroadcasts'), ['part' => 'status', 'id' => $id])->throw()->json('items.0.status', []);
        if (($status['privacyStatus'] ?? null) !== 'public') throw new \RuntimeException('YouTube Live is not confirmed public. Check channel permissions and visibility.');
        return (string) ($status['lifeCycleStatus'] ?? 'unknown');
    }

    public function completeBroadcast(string $id): string
    {
        $status = $this->request()->get($this->url('liveBroadcasts'), ['part' => 'status', 'id' => $id])
            ->throw()->json('items.0.status.lifeCycleStatus');
        if ($status === 'complete') return 'ended';
        if (in_array($status, ['live', 'testing'], true)) {
            $this->transitionBroadcast($id, 'complete');
            return 'ended';
        }
        if (in_array($status, ['created', 'ready'], true)) return 'not_started';
        throw new \RuntimeException('YouTube broadcast completion status is unavailable.');
    }

    /** @return array<int, array<string, mixed>> */
    public function uploadedVideos(): array
    {
        $channel = $this->channel();
        $playlistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        if (! is_string($playlistId)) return [];
        $ids = [];
        $since = now()->subDay();
        $page = null;
        do {
            $query = ['part' => 'contentDetails,snippet', 'playlistId' => $playlistId, 'maxResults' => 50];
            if ($page) $query['pageToken'] = $page;
            $data = $this->request()->get($this->url('playlistItems'), $query)->throw()->json();
            $older = false;
            foreach ($data['items'] ?? [] as $item) {
                $date = $item['contentDetails']['videoPublishedAt'] ?? $item['snippet']['publishedAt'] ?? null;
                if (! $date || \Illuminate\Support\Carbon::parse($date)->lt($since)) { $older = true; continue; }
                if (is_string($item['contentDetails']['videoId'] ?? null)) $ids[] = $item['contentDetails']['videoId'];
            }
            $page = $data['nextPageToken'] ?? null;
        } while ($page && ! $older);
        $videos = [];
        foreach (array_chunk(array_values(array_unique($ids)), 50) as $chunk) {
            $data = $this->request()->get($this->url('videos'), ['part' => 'snippet,status,contentDetails,liveStreamingDetails', 'id' => implode(',', $chunk)])->throw()->json();
            $videos = [...$videos, ...($data['items'] ?? [])];
        }
        return $videos;
    }

    public function videosByIds(array $ids): array
    {
        $videos = [];
        foreach (array_chunk(array_unique($ids), 50) as $chunk) {
            $videos = [...$videos, ...$this->request()->get($this->url('videos'), ['part' => 'snippet,status,contentDetails,liveStreamingDetails', 'id' => implode(',', $chunk)])->throw()->json('items', [])];
        }
        return $videos;
    }

    public function replyComment(string $parent,string $body): array
    {
        return $this->request()->timeout(30)->post($this->url('comments').'?part=snippet',['snippet'=>['parentId'=>$parent,'textOriginal'=>$body]])->throw()->json();
    }

    public function commentThreads(string $channel,?string $page=null):array
    {
        return $this->request()->timeout(30)->get($this->url('commentThreads'),array_filter(['part'=>'id,snippet','allThreadsRelatedToChannelId'=>$channel,'maxResults'=>100,'order'=>'time','textFormat'=>'plainText','pageToken'=>$page],fn($value)=>$value!==null))->throw()->json();
    }
    public function commentReplies(string $parent,?string $page=null):array
    {
        return $this->request()->timeout(30)->get($this->url('comments'),array_filter(['part'=>'id,snippet','parentId'=>$parent,'maxResults'=>100,'textFormat'=>'plainText','pageToken'=>$page],fn($value)=>$value!==null))->throw()->json();
    }

    private function tokenPayload(array $payload): array
    {
        $data = ['access_token' => $payload['access_token'], 'token_type' => $payload['token_type'] ?? 'Bearer'];
        if (! empty($payload['refresh_token'])) $data['refresh_token'] = $payload['refresh_token'];
        if (isset($payload['expires_in'])) $data['expires_at'] = now()->addSeconds((int) $payload['expires_in'])->timestamp;
        if (! empty($payload['scope'])) $data['scope'] = $payload['scope'];
        return $data;
    }

    private function request(?array $credentials = null): PendingRequest
    {
        if ($credentials === null && ! $this->connections->connected('youtube')) throw new YouTubeConnectionExpired('YouTube is disconnected.');
        $credentials ??= $this->connections->credentials('youtube');
        $expiresAt = (int) ($credentials['expires_at'] ?? 0);
        if ((! is_string($credentials['access_token'] ?? null) || $expiresAt && $expiresAt <= now()->addMinute()->timestamp) && ! empty($credentials['refresh_token'])) {
            $oauth = app(OAuthAppCredentials::class)->get('youtube');
            $response = Http::asForm()->timeout(30)->post((string) config('publishing.youtube.oauth_token'), [
                'client_id' => $oauth['client_id'],
                'client_secret' => $oauth['client_secret'],
                'refresh_token' => $credentials['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);
            if (! $response->successful()) {
                if ($response->json('error') === 'invalid_grant') throw new YouTubeConnectionExpired('YouTube refresh token expired or was revoked.');
                throw new \RuntimeException('YouTube OAuth token refresh failed (HTTP '.$response->status().').');
            }
            if (! $this->connections->connected('youtube')) throw new YouTubeConnectionExpired('YouTube was disconnected during token refresh.');
            $refreshed = $this->tokenPayload([...$response->json(), 'refresh_token' => $credentials['refresh_token']]);
            $this->connections->saveCredentials('youtube', $refreshed);
            $credentials = [...$credentials, ...$refreshed];
        }
        if (! is_string($credentials['access_token'] ?? null) || $credentials['access_token'] === '') throw new YouTubeConnectionExpired('YouTube is not connected.');
        return Http::acceptJson()->withToken($credentials['access_token'])->timeout(3500);
    }

    private function markHealthy(array $channel): void
    {
        $social = $this->settings->get('social_connections', []);
        if (! is_array($social['youtube'] ?? null)) return;
        unset($social['youtube']['revoked_at'], $social['youtube']['last_error_at'], $social['youtube']['last_error_reason']);
        $social['youtube']['checked_at'] = now()->toIso8601String();
        if (is_string($channel['snippet']['title'] ?? null) && $channel['snippet']['title'] !== '') $social['youtube']['display_name'] = $channel['snippet']['title'];
        $customUrl = $channel['snippet']['customUrl'] ?? null;
        $social['youtube']['public_url'] = is_string($customUrl) && $customUrl !== ''
            ? 'https://www.youtube.com/'.$customUrl
            : 'https://www.youtube.com/channel/'.$channel['id'];
        $this->settings->update(['social_connections' => $social]);
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
