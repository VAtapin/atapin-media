<?php

namespace App\Services\Publishing;

use App\Models\SourceRecord;
use App\Services\PublicContent;
use Illuminate\Support\Str;

class TelegramWebsite
{
    public function __construct(private readonly PublicContent $content, private readonly ConnectionStore $connections) {}

    public function announcement(SourceRecord $record): array
    {
        if (! $this->content->visible($record)) throw new \RuntimeException('Telegram video links require a published Website material.');
        $url = $this->content->card($record)['url'];
        $description = ($record->metadata['platform_metadata']['telegram']['body']??null) ?: ($record->metadata['short_description'] ?? '');
        $text = trim(PlatformText::value($record,'telegram','title')."\n\n".(is_string($description) ? strip_tags($description) : ''));
        $buttons = [['text' => __('publishing.watch_on_site'), 'url' => $url]];
        $connection = $this->connections->connection('telegram');
        $username = $connection['bot_username'] ?? '';
        if (($connection['mini_app_enabled'] ?? false) && is_string($username) && preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $username)) {
            array_unshift($buttons, ['text' => __('publishing.open_in_telegram'), 'url' => 'https://t.me/'.$username.'?startapp=record_'.$record->id]);
        }
        return ['text' => Str::limit($text, 1024, '…'), 'reply_markup' => ['inline_keyboard' => [$buttons]]];
    }

    public function materialUrl(string $token): string
    {
        abort_unless(preg_match('/^record_([1-9][0-9]{0,18})$/', $token, $matches), 404);
        $record = $this->content->query()->whereIn('kind', ['video', 'short', 'post'])->findOrFail($matches[1]);
        return $this->content->card($record)['url'];
    }
}
