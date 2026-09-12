<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\Storage;

class ContentMetadataImporter
{
    public function record(string $source, string $id, string $kind, string $title, string $body, array $metadata): SourceRecord
    {
        // Re-import never overwrites the owner's reviewed text or assignment.
        $record = SourceRecord::firstOrCreate(['source' => $source, 'source_id' => $id], [
            'kind' => $kind, 'title' => $title, 'body' => $body, 'metadata' => $metadata, 'status' => 'unsorted',
        ]);
        if (! $record->wasRecentlyCreated && ! empty($metadata['media_ids'])) {
            $existing = $record->metadata;
            $existing['media_ids'] = array_values(array_unique([...($existing['media_ids'] ?? []), ...$metadata['media_ids']]));
            $record->update(['metadata' => $existing]);
        }
        return $record;
    }

    public function comments(string $source, string $parent, array $comments, array $metadata = []): void
    {
        foreach ($comments as $comment) {
            if (! is_array($comment)) continue;
            $text = $comment['text'] ?? $comment['content'] ?? '';
            $id = (string) ($comment['id'] ?? hash('sha256', json_encode($comment)));
            $this->record($source, 'comment:'.$parent.':'.$id, 'comment', mb_substr($text, 0, 120), $text,
                [...$metadata, 'parent_source_id' => $parent, 'parent_comment_id' => $comment['parent'] ?? null,
                    'author' => $comment['author'] ?? null, 'timestamp' => $comment['timestamp'] ?? null, 'raw' => $comment]);
        }
    }

    public function scan(ImportRun $run, string $root): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) continue;
            $extension = strtolower($file->getExtension());
            if ($file->getSize() > 64 * 1024 * 1024) continue;
            try {
                if ($extension === 'csv' && str_contains(strtolower($file->getFilename()), 'video')) {
                    $this->csv($run, $file->getPathname()); continue;
                }
                if ($extension !== 'json') continue;
                $data = json_decode(file_get_contents(ImportPath::resolve($root, $file->getPathname())), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($data)) continue;
                if (($data['schema'] ?? null) === 'atapin-content/v1') {
                    foreach ($data['records'] ?? [] as $record) $this->normalized($run, $record, $root, dirname($file->getPathname()));
                } elseif (isset($data['youtube']) || (isset($data['id'], $data['title']) && isset($data['extractor_key']))) {
                    $info = $data['youtube'] ?? $data;
                    $info['id'] ??= basename(dirname($file->getPathname()));
                    $this->video($run, $info, dirname($file->getPathname()), $root,
                        in_array('shorts', $data['sources'] ?? [], true));
                } elseif (isset($data['id'], $data['text']) && (isset($data['raw']) || $file->getFilename() === 'post.json')) {
                    $this->normalized($run, ['source' => 'youtube', 'id' => $data['id'], 'kind' => 'post',
                        'title' => mb_substr($data['text'], 0, 120), 'body' => $data['text'], 'metadata' => $data], $root, dirname($file->getPathname()));
                }
            } catch (\Throwable $error) {
                $notes = $run->fresh()->notes ?? [];
                if (count($notes) < 100) $notes[] = $file->getFilename().': '.$error->getMessage();
                $run->update(['notes' => $notes]);
            }
        }
    }

    private function assets(string $directory, string $root, ?string $name = null): array
    {
        $ids = [];
        $prefix = str_replace(DIRECTORY_SEPARATOR, '/', substr(realpath($directory), strlen(realpath(config('platform.import_inbox_root'))) + 1));
        foreach (Media::where('disk', 'import-inbox')->where('path', 'like', $prefix.'/%')->get() as $media) {
            if (! str_starts_with($media->path, $prefix.'/')) continue;
            if ($name !== null && pathinfo($media->original_name, PATHINFO_FILENAME) !== $name) continue;
            if (in_array($media->kind, ['video', 'audio', 'image', 'pdf'], true)) $ids[] = $media->id;
        }
        return $ids;
    }

    private function video(ImportRun $run, array $info, string $directory, string $root, bool $short = false): void
    {
        $source = $run->source === 'youtube-service' || str_starts_with(strtolower($info['extractor_key'] ?? ''), 'youtube') || isset($info['channel_id']) && isset($info['upload_date']) && isset($info['webpage_url']) && str_contains($info['webpage_url'], 'youtube.com') ? 'youtube' : $run->source;
        $id = (string) $info['id'];
        $url = $info['webpage_url'] ?? '';
        $short = $short || str_contains($url, '/shorts/');
        $metadata = ['raw' => $info, 'media_ids' => $this->assets($directory, $root), 'url' => $url,
            'import_id' => $run->id, 'target_profile' => $run->target_profile ?? 'mixed', 'published_at' => $info['upload_date'] ?? null];
        $record = $this->record($source, $id, $short ? 'short' : 'video', $info['title'] ?? $id, $info['description'] ?? '', $metadata);
        $run->increment($record->wasRecentlyCreated ? 'imported' : 'skipped');
        $comments = $info['comments'] ?? [];
        if (is_file($directory.'/comments.json')) {
            $path = ImportPath::resolve($root, $directory.'/comments.json');
            if (filesize($path) <= 64 * 1024 * 1024) {
                $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                $comments = $data['comments'] ?? $data;
            }
        }
        if (is_array($comments)) $this->comments($source, $id, $comments, ['import_id' => $run->id]);
    }

    private function normalized(ImportRun $run, array $data, string $root, string $directory): void
    {
        $kind = $data['kind'] ?? 'post';
        if (! in_array($kind, ['video', 'short', 'post', 'poll', 'comment'], true) || empty($data['id'])) return;
        $source = $data['source'] ?? $run->source;
        if (! is_string($source) || strlen($source) > 32) throw new \RuntimeException('Invalid content source.');
        $metadata = $data['metadata'] ?? [];
        $metadata['import_id'] = $run->id; $metadata['raw'] = $data;
        $metadata['media_ids'] = $this->assets($directory, $root);
        $record = $this->record($source, (string) $data['id'], $kind, $data['title'] ?? $data['id'], $data['body'] ?? '', $metadata);
        $run->increment($record->wasRecentlyCreated ? 'imported' : 'skipped');
        $poll = $data['poll'] ?? $data['metadata']['raw']['backstageAttachment']['pollRenderer'] ?? null;
        if (is_array($poll)) $this->record($source, 'poll:'.$data['id'], 'poll', $data['title'] ?? $data['id'], $data['body'] ?? '',
            ['parent_source_id' => $data['id'], 'poll' => $poll, 'import_id' => $run->id]);
        $this->comments($source, (string) $data['id'], $data['comments'] ?? [], ['import_id' => $run->id]);
    }

    private function csv(ImportRun $run, string $path): void
    {
        $stream = fopen($path, 'rb');
        if (! $stream) return;
        try {
            $headers = fgetcsv($stream, escape: '');
            if (! $headers) return;
            $headers = array_map(fn ($key) => preg_replace('/[^a-z0-9]/', '', strtolower(ltrim($key, "\xEF\xBB\xBF"))), $headers);
            while (($values = fgetcsv($stream, escape: '')) !== false) {
                if (count($values) !== count($headers)) continue;
                $row = array_combine($headers, $values);
                $id = $row['videoid'] ?? $row['id'] ?? null;
                if (! $id) continue;
                $record = $this->record('youtube', $id, 'video', $row['videotitle'] ?? $row['title'] ?? $id,
                    $row['videodescription'] ?? $row['description'] ?? '', ['raw' => $row, 'import_id' => $run->id,
                        'media_ids' => array_values(array_unique([...$this->assets(dirname($path), dirname($path), $id),
                            ...$this->assets(dirname($path), dirname($path), $row['videotitle'] ?? $row['title'] ?? $id)]))]);
                $run->increment($record->wasRecentlyCreated ? 'imported' : 'skipped');
            }
        } finally { fclose($stream); }
    }
}
