<?php

namespace App\Services\Publishing\Connectors;

use App\Contracts\PublishingConnector;
use App\Models\Publication;
use App\Services\Publishing\{ConnectionStore, MediaResolver, UnsupportedCapability};
use Illuminate\Support\Facades\Http;

class TelegramConnector implements PublishingConnector
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
        $base = rtrim((string) config('publishing.telegram.api_base'), '/').'/bot'.$token;
        $caption = trim($record->title."\n\n".(string) ($record->body ?? ''));
        if (in_array($record->kind, ['video', 'short'], true) && ($video = app(MediaResolver::class)->video($record))) {
            $response = Http::timeout(300)->attach('video', fopen($video['path'], 'rb'), basename($video['path']))->post($base.'/sendVideo', ['chat_id' => $chat, 'caption' => $caption]);
        } elseif ($image = app(MediaResolver::class)->image($record)) {
            $response = Http::timeout(120)->attach('photo', fopen($image['path'], 'rb'), basename($image['path']))->post($base.'/sendPhoto', ['chat_id' => $chat, 'caption' => $caption]);
        } else {
            $response = Http::timeout(60)->post($base.'/sendMessage', ['chat_id' => $chat, 'text' => $caption]);
        }
        $response->throw();
        $id = $response->json('result.message_id');
        if (! is_numeric($id)) throw new \RuntimeException('Telegram returned no message ID.');
        return ['external_id' => (string) $id, 'remote_status' => 'published'];
    }
}
