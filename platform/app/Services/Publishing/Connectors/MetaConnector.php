<?php

namespace App\Services\Publishing\Connectors;

use App\Contracts\{ManagesPublications, PublishingConnector};
use App\Models\Publication;
use App\Services\Publishing\{ConnectionStore, MediaResolver, PublicationPending, UnsupportedCapability, VideoRenderer};
use Illuminate\Support\Facades\Http;

class MetaConnector implements PublishingConnector, ManagesPublications
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
        return ['video' => true, 'short' => true, 'post' => true, 'live' => false];
    }

    public function actions(): array { return $this->provider === 'facebook' ? ['update', 'hide', 'delete'] : []; }
    public function manage(Publication $publication, string $action): array
    {
        if (! in_array($action, $this->actions(), true)) throw new UnsupportedCapability('This Meta destination does not support the requested operation.');
        $credentials = $this->connections->credentials($this->provider);
        $request = Http::withToken($credentials['access_token'] ?? $credentials['api_key'])->timeout(60);
        $url = 'https://graph.facebook.com/'.config('publishing.meta.api_version').'/'.$publication->external_id;
        if ($action === 'delete') {
            $response = $request->delete($url)->throw();
            if ($response->json() !== true && $response->json('success') !== true) throw new \RuntimeException('Facebook did not confirm deletion.');
        }
        else {
            $type = $publication->payload['facebook_type'] ?? 'post';
            $caption = $this->caption($publication->record);
            if ($type === 'photo' && $action === 'hide') throw new UnsupportedCapability('Facebook photo visibility cannot be reversibly made private through this workflow. Delete it explicitly on the platform if required.');
            if ($type === 'photo') {
                $postId = $publication->payload['facebook_post_id'] ?? $request->get($url, ['fields' => 'page_story_id'])->throw()->json('page_story_id');
                if (! is_string($postId) || $postId === '') throw new UnsupportedCapability('Facebook returned no editable Page story for this photo.');
                $url = 'https://graph.facebook.com/'.config('publishing.meta.api_version').'/'.$postId;
            }
            $data = $type === 'video' ? ['title' => $publication->record->title, 'description' => $caption] : ['message' => $caption];
            if ($type !== 'photo') $data[$type === 'video' ? 'published' : 'is_published'] = $action !== 'hide';
            $request->post($url, $data)->throw();
            if ($type !== 'photo') {
                $field = $type === 'video' ? 'published' : 'is_published';
                $visible = $request->get($url, ['fields' => $field])->throw()->json($field);
                if ($visible !== ($action !== 'hide')) throw new \RuntimeException('Facebook did not confirm the requested publication visibility.');
            }
        }
        return ['remote_status' => $action === 'delete' ? 'deleted' : ($action === 'hide' ? 'hidden' : 'published')];
    }

    public function publish(Publication $publication): array
    {
        $record = $publication->record;
        if (! $this->connections->connected($this->provider)) throw new UnsupportedCapability('The destination is disconnected.');
        $credentials = $this->connections->credentials($this->provider);
        $account = $this->connections->connection($this->provider)['external_id'] ?? null;
        $token = $credentials['access_token'] ?? $credentials['api_key'] ?? null;
        if (! is_string($account) || $account === '' || ! is_string($token) || $token === '') throw new \RuntimeException(ucfirst($this->provider).' connection is incomplete.');
        if ($record->publishingKind() === 'live') throw new UnsupportedCapability(ucfirst($this->provider).' Live output is not configured.');
        return $this->provider === 'facebook' ? $this->facebook($publication, $account, $token) : $this->instagram($publication, $account, $token);
    }

    private function facebook(Publication $publication, string $page, string $token): array
    {
        $record = $publication->record;
        $base = 'https://graph.facebook.com/'.config('publishing.meta.api_version');
        if ($publication->external_id && ($publication->payload['facebook_type'] ?? null) === 'video') return $this->facebookVideoStatus($publication, $base, $token);
        if ($publication->external_id) {
            Http::withToken($token)->timeout(30)->get($base.'/'.$publication->external_id, ['fields' => 'id'])->throw();
            return ['external_id' => $publication->external_id, 'external_url' => 'https://www.facebook.com/'.$publication->external_id, 'remote_status' => 'published'];
        }
        $asset = app(MediaResolver::class)->video($record);
        if (! $asset && app(MediaResolver::class)->audio($record)) $asset = app(VideoRenderer::class)->video($record);
        if ($asset) {
            $type = 'video';
            $response = Http::timeout(300)->withToken($token)->attach('source', fopen($asset['path'], 'rb'), basename($asset['path']))
                ->post($base.'/'.$page.'/videos', ['description' => $this->caption($record), 'title' => $record->title, 'published' => 'true']);
        } elseif ($image = app(MediaResolver::class)->image($record)) {
            $type = 'photo';
            $response = Http::timeout(120)->withToken($token)->attach('source', fopen($image['path'], 'rb'), basename($image['path']))
                ->post($base.'/'.$page.'/photos', ['caption' => $this->caption($record), 'published' => 'true']);
        } else {
            $type = 'post';
            $response = Http::timeout(60)->withToken($token)->post($base.'/'.$page.'/feed', ['message' => $this->caption($record), 'published' => true]);
        }
        $response->throw();
        $id = $response->json('id') ?? $response->json('post_id');
        if (! is_string($id)) throw new \RuntimeException('Facebook returned no publication ID.');
        $publication->update(['external_id' => $id, 'payload' => [...($publication->payload ?? []), 'facebook_type' => $type, 'facebook_post_id' => $response->json('post_id')]]);
        if ($type === 'video') return $this->facebookVideoStatus($publication, $base, $token);
        return ['external_id' => $id, 'external_url' => 'https://www.facebook.com/'.rawurlencode($id), 'remote_status' => 'published'];
    }

    private function facebookVideoStatus(Publication $publication, string $base, string $token): array
    {
        $data = Http::timeout(30)->withToken($token)->get($base.'/'.$publication->external_id, ['fields' => 'status,published,permalink_url'])->throw()->json();
        $status = $data['status']['video_status'] ?? 'unknown';
        if ($status === 'error') throw new \RuntimeException('Facebook video processing failed.');
        if ($status !== 'ready') throw new PublicationPending();
        if (($data['published'] ?? null) !== true) throw new \RuntimeException('Facebook processed the video but did not confirm publication.');
        return ['external_id' => $publication->external_id, 'external_url' => $data['permalink_url'] ?? 'https://www.facebook.com/'.$publication->external_id, 'remote_status' => 'published'];
    }

    private function instagram(Publication $publication, string $account, string $token): array
    {
        $record = $publication->record;
        $image = app(MediaResolver::class)->image($record);
        $video = app(MediaResolver::class)->video($record);
        if (! $video && app(MediaResolver::class)->audio($record)) $video = app(VideoRenderer::class)->video($record);
        if (! $video && ! $image) $video = app(VideoRenderer::class)->video($record);
        $media = $video['media'] ?? $image['media'] ?? null;
        $url = $media?->publicUrl();
        if (! $media || ! is_string($url)) throw new UnsupportedCapability('Instagram requires a public image or video asset.');
        $base = 'https://graph.facebook.com/'.config('publishing.meta.api_version');
        $payload = ['caption' => $this->caption($record), 'access_token' => $token];
        if ($video) { $payload['media_type'] = 'REELS'; $payload['video_url'] = $url; }
        else { $payload['image_url'] = $url; }
        $container = $publication->payload['container_id'] ?? null;
        if (! $container) {
            $container = Http::timeout(120)->post($base.'/'.$account.'/media', $payload)->throw()->json('id');
            if (! is_string($container)) throw new \RuntimeException('Instagram returned no media container ID.');
            $publication->update(['payload' => [...($publication->payload ?? []), 'container_id' => $container]]);
        }
        if (! is_string($container)) throw new \RuntimeException('Instagram returned no media container ID.');
        $id = $publication->external_id;
        if (! $id) {
            $status = Http::timeout(30)->withToken($token)->get($base.'/'.$container, ['fields' => 'status_code,status'])->throw()->json();
            if (($status['status_code'] ?? null) === 'IN_PROGRESS') throw new PublicationPending();
            if (($status['status_code'] ?? null) !== 'FINISHED') throw new \RuntimeException('Instagram container is not publishable: '.($status['status_code'] ?? 'unknown'));
            $id = Http::timeout(120)->post($base.'/'.$account.'/media_publish', ['creation_id' => $container, 'access_token' => $token])->throw()->json('id');
            if (! is_string($id)) throw new \RuntimeException('Instagram returned no publication ID.');
            $publication->update(['external_id' => $id]);
        }
        if (! is_string($id)) throw new \RuntimeException('Instagram returned no publication ID.');
        $url = Http::timeout(30)->withToken($token)->get($base.'/'.$id, ['fields' => 'permalink'])->throw()->json('permalink');
        if (! is_string($url) || $url === '') throw new \RuntimeException('Instagram did not return a publication permalink; the media ID has been retained.');
        return ['external_id' => $id, 'external_url' => $url, 'remote_status' => 'published', 'payload' => ['container_id' => $container]];
    }

    private function caption(\App\Models\SourceRecord $record): string
    {
        return trim($record->title."\n\n".(string) ($record->body ?? ''));
    }
}
