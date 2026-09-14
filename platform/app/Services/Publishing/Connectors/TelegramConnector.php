<?php

namespace App\Services\Publishing\Connectors;

use App\Contracts\{ManagesPublications, PublishingConnector};
use App\Models\Publication;
use App\Services\Publishing\{ConnectionStore, MediaResolver, TelegramWebsite, UnsupportedCapability};
use Illuminate\Support\Facades\Http;

class TelegramConnector implements PublishingConnector, ManagesPublications
{
    public function __construct(private readonly ConnectionStore $connections)
    {
    }

    public function provider(): string
    {
        return 'telegram';
    }

    public function capabilities(): array
    {
        return ['video' => true, 'short' => true, 'post' => true, 'live' => false];
    }

    public function actions(): array { return ['update', 'delete']; }
    public function manage(Publication $publication, string $action): array
    {
        $credentials = $this->connections->credentials('telegram');
        $base = rtrim((string) config('publishing.telegram.api_base'), '/').'/bot'.($credentials['api_key'] ?? $credentials['access_token']);
        $data = ['chat_id' => $this->connections->connection('telegram')['external_id'], 'message_id' => $publication->external_id];
        if ($action === 'delete') $method = 'deleteMessage';
        elseif ($action === 'update') {
            $caption = \App\Services\Publishing\PlatformText::caption($publication->record,'telegram');
            if ($publication->payload['telegram_website_link'] ?? false) {
                $announcement = app(TelegramWebsite::class)->announcement($publication->record);
                $caption = $announcement['text'];
                $data['reply_markup'] = $announcement['reply_markup'];
            }
            // Honor the actual sent message type, including an explicitly saved false.
            $media = $publication->payload['telegram_media'] ?? (bool) (app(MediaResolver::class)->video($publication->record) || app(MediaResolver::class)->image($publication->record) || app(MediaResolver::class)->audio($publication->record));
            $method = $media ? 'editMessageCaption' : 'editMessageText';
            $data[$media ? 'caption' : 'text'] = $caption;
        } else throw new UnsupportedCapability('Telegram has no reversible private visibility for a channel message.');
        $response = Http::timeout(60)->post($base.'/'.$method, $data);
        if (! str_contains((string) $response->json('description'), 'message is not modified')) {
            $response->throw();
            if ($response->json('ok') !== true) throw new \RuntimeException('Telegram did not confirm the requested operation.');
        }
        return ['remote_status' => $action === 'delete' ? 'deleted' : 'published'];
    }

    public function publish(Publication $publication): array
    {
        $record = $publication->record;
        if (! $this->connections->connected('telegram')) throw new UnsupportedCapability('Telegram is disconnected.');
        $connection = $this->connections->connection('telegram');
        $credentials = $this->connections->credentials('telegram');
        $token = $credentials['api_key'] ?? $credentials['access_token'] ?? null;
        $chat = $connection['external_id'] ?? null;
        if (! is_string($token) || $token === '' || ! is_string($chat) || $chat === '') throw new \RuntimeException('Telegram connection is incomplete.');
        if ($record->publishingKind() === 'live') throw new UnsupportedCapability('Telegram Live output is not configured.');
        if ($publication->external_id) return ['external_id' => $publication->external_id, 'remote_status' => 'published'];
        $base = rtrim((string) config('publishing.telegram.api_base'), '/').'/bot'.$token;
        $publicChat = Http::timeout(30)->post($base.'/getChat', ['chat_id' => $chat])->throw()->json('result', []);
        if (empty($publicChat['username']) || ! in_array($publicChat['type'] ?? null, ['channel', 'supergroup'], true)) throw new \RuntimeException('Telegram requires a public channel or group for automatic public distribution.');
        $caption = \App\Services\Publishing\PlatformText::caption($record,'telegram');
        $websiteLink = in_array($record->publishingKind(), ['video', 'short'], true) || (bool) app(MediaResolver::class)->video($record);
        $media = false;
        if ($websiteLink) {
            $announcement = app(TelegramWebsite::class)->announcement($record);
            $data = ['chat_id' => $chat, 'reply_markup' => json_encode($announcement['reply_markup'], JSON_THROW_ON_ERROR)];
            if ($image = app(MediaResolver::class)->image($record)) {
                $media = true;
                $response = Http::timeout(120)->attach('photo', fopen($image['path'], 'rb'), basename($image['path']))->post($base.'/sendPhoto', [...$data, 'caption' => $announcement['text']]);
            } else {
                $response = Http::timeout(60)->post($base.'/sendMessage', [...$data, 'text' => $announcement['text']]);
            }
        } elseif ($audio = app(MediaResolver::class)->audio($record)) {
            $media = true;
            $response = Http::timeout(300)->attach('audio', fopen($audio['path'], 'rb'), basename($audio['path']))->post($base.'/sendAudio', ['chat_id' => $chat, 'caption' => $caption]);
        } elseif ($image = app(MediaResolver::class)->image($record)) {
            $media = true;
            $response = Http::timeout(120)->attach('photo', fopen($image['path'], 'rb'), basename($image['path']))->post($base.'/sendPhoto', ['chat_id' => $chat, 'caption' => $caption]);
        } else {
            $response = Http::timeout(60)->post($base.'/sendMessage', ['chat_id' => $chat, 'text' => $caption]);
        }
        $response->throw();
        if ($response->json('ok') !== true) throw new \RuntimeException('Telegram did not confirm publication.');
        $id = $response->json('result.message_id');
        if (! is_numeric($id)) throw new \RuntimeException('Telegram returned no message ID.');
        $publication->update(['external_id' => (string) $id, 'payload' => [...($publication->payload ?? []), 'telegram_media' => $media, 'telegram_website_link' => $websiteLink]]);
        return ['external_id' => (string) $id, 'remote_status' => 'published'];
    }
}
