<?php
namespace App\Services\Importing;

use App\Models\ImportRun;

/** Small CSV rows are indexed first so each video/post commits with its related data. */
class TakeoutObjectMetadata
{
    public array $texts = [];
    private array $comments = [];
    private array $chats = [];
    private array $coordinates = [];
    private array $settings = [];

    public function load(ImportRun $run, array $files): void
    {
        $this->texts = $this->comments = $this->chats = $this->coordinates = $this->settings = [];
        foreach ($files as $file) {
            $folder = TakeoutCsv::key(basename(dirname($file['entry'])));
            $name = TakeoutCsv::key(pathinfo($file['entry'], PATHINFO_FILENAME));
            if (strtolower(pathinfo($file['entry'], PATHINFO_EXTENSION)) !== 'csv') continue;
            $type = match (true) {
                in_array($folder, ['videometadaten','videometadata']) && preg_match('/^(videotexte|videotexts)\d*$/', $name) => 'texts',
                in_array($folder, ['kommentare','comments']) && preg_match('/^(kommentare|comments)\d*$/', $name) => 'comments',
                $folder === 'livechats' => 'chats',
                in_array($folder, ['videometadaten','videometadata']) && str_starts_with($name, 'videoaufzeichnungen') => 'coordinates',
                in_array($folder, ['beitrage','posts']) && str_starts_with($name, 'einstellungenfurkommentare') => 'settings',
                default => null,
            };
            if (! $type) continue;
            foreach (app(TakeoutCsv::class)->rows($file['path']) as $row) {
                app(ImportProgress::class)->checkpoint($run);
                $id = $row['videoid'] ?? '';
                $id = $id ?: ($row['beitragsid'] ?? $row['postid'] ?? '');
                if ($id) $this->{$type}[$id][] = $row;
            }
        }
    }

    public function attach(string $id, string $kind, string $title, string $body, callable $record, callable $comment): void
    {
        foreach ($this->comments[$id] ?? [] as $row) $comment($row);
        foreach ($this->chats[$id] ?? [] as $row) {
            $chatId = $row['livechatid'] ?? '';
            if (! $chatId) continue;
            $parsed = app(TakeoutText::class)->parse($row['textfurdenlivechat'] ?? $row['livechattext'] ?? '');
            $record('live-chat:'.$chatId, 'live_chat', mb_substr($parsed['text'], 0, 120) ?: $chatId, $parsed['text'], [
                'parent_source_id' => $id, 'author' => $row['kanalid'] ?? null,
                'timestamp' => $row['zeitstempelbeierstellungdeslivechats'] ?? null, 'price' => $row['preis'] ?? null,
                'takeout_data' => ['live_chat' => $row], 'structured_text' => $parsed['runs'], 'references' => $parsed['references'],
            ]);
        }
        foreach ($this->coordinates[$id] ?? [] as $row) $record($id, $kind, $title, $body, ['takeout_data' => ['recording' => $row]]);
        foreach ($this->settings[$id] ?? [] as $row) $record($id, $kind, $title, $body, ['takeout_data' => ['comment_settings' => $row]]);
    }

    public function fingerprint(string $id): string
    {
        return hash('sha256', json_encode([$this->texts[$id] ?? [], $this->comments[$id] ?? [], $this->chats[$id] ?? [], $this->coordinates[$id] ?? [], $this->settings[$id] ?? []], JSON_THROW_ON_ERROR));
    }
}
