<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Models\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContentMetadataImporter
{
    public function record(string $source, string $id, string $kind, string $title, string $body, array $metadata): SourceRecord
    {
        return app(ImportedRecordMerger::class)->merge($source,$id,$kind,$title,$body,$metadata);
    }

    public function comments(string $source, string $parent, array $comments, array $metadata = []): void
    {
        $run = isset($metadata['import_id']) ? ImportRun::find($metadata['import_id']) : null;
        foreach ($comments as $comment) {
            if ($run) app(ImportProgress::class)->checkpoint($run);
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
            app(ImportProgress::class)->checkpoint($run);
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
                if (isset($data['id'], $data['ordered_items']) || (($data['_type']??'') === 'playlist' && isset($data['id'], $data['entries']))) {
                    $source = $data['source'] ?? ($run->source === 'youtube-service' || isset($data['ordered_items']) || str_starts_with(strtolower($data['extractor_key']??''),'youtube') ? 'youtube' : $run->source);
                    $this->playlist($source, $data, ['import_id'=>$run->id]);
                } elseif (($data['schema'] ?? null) === 'atapin-content/v1') {
                    foreach ($data['records'] ?? [] as $record) {
                        app(ImportProgress::class)->checkpoint($run);
                        $this->normalized($run, $record, $root, dirname($file->getPathname()), count($data['records']) === 1);
                    }
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
                if ($error instanceof ImportStopped) throw $error;
                $notes = $run->fresh()->notes ?? [];
                if (count($notes) < 100) $notes[] = $file->getFilename().': '.$error->getMessage();
                $run->update(['notes' => $notes]);
            }
        }
    }

    public function playlist(string $source, array $data, array $metadata = []): Collection
    {
        if (count($data['ordered_items']??$data['entries']??[]) > 100000) throw new \RuntimeException('Playlist exceeds 100000 positions.');
        return DB::transaction(function () use ($source, $data, $metadata) {
            $collection = Collection::firstOrCreate(['source'=>$source,'source_id'=>(string) $data['id']],
                ['title'=>mb_substr($data['title']??$data['id'],0,255),'description'=>$data['description']??'', 'metadata'=>[...$metadata,'raw'=>$data]]);
            $collection->items()->delete();
            foreach ($data['ordered_items'] ?? $data['entries'] ?? [] as $index => $item) $collection->items()->create([
                'position'=>(int) ($item['position']??$index+1), 'source_id'=>$item['id']??null,
                'title'=>isset($item['title']) ? mb_substr($item['title'],0,255) : null, 'availability'=>$item['availability']??null,
            ]);
            return $collection;
        });
    }

    private function assets(string $directory, string $root, ?string $name = null): array
    {
        $ids = [];
        $prefix = str_replace(DIRECTORY_SEPARATOR, '/', substr(realpath($directory), strlen(realpath(config('platform.import_inbox_root'))) + 1));
        $originalIds = \App\Models\MediaOriginal::where('disk','import-inbox')->where('path','like',$prefix === '' ? '%' : $prefix.'/%')->get()
            ->filter(fn ($original) => ($prefix === '' || str_starts_with($original->path,$prefix.'/')) && ($name === null || pathinfo($original->path,PATHINFO_FILENAME) === $name))->pluck('media_id');
        $ids = Media::whereIn('id',$originalIds)->get()->filter(fn ($media) => in_array($media->kind,['video','audio','image','pdf'],true) || in_array(strtolower(pathinfo($media->original_name,PATHINFO_EXTENSION)),['srt','vtt','ass'],true))->pluck('id')->all();
        foreach (Media::where('disk', 'import-inbox')->where('path', 'like', $prefix === '' ? '%' : $prefix.'/%')->get() as $media) {
            if ($prefix !== '' && ! str_starts_with($media->path, $prefix.'/')) continue;
            if ($name !== null && pathinfo($media->original_name, PATHINFO_FILENAME) !== $name) continue;
            if (in_array($media->kind, ['video', 'audio', 'image', 'pdf'], true) || in_array(strtolower(pathinfo($media->path,PATHINFO_EXTENSION)),['srt','vtt','ass'],true)) $ids[] = $media->id;
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
        $assets = Media::whereIn('id',$metadata['media_ids'])->get();
        $primary = $assets->firstWhere('kind','video');
        if ($primary) {
            $primary->update(['asset_role'=>'video']);
            foreach ($assets as $asset) if ($asset->id !== $primary->id && ($asset->kind === 'image' || in_array(strtolower(pathinfo($asset->path,PATHINFO_EXTENSION)),['srt','vtt','ass'],true))) {
                if ($asset->parent_id === null || $asset->parent_id === $primary->id) $asset->update(['parent_id'=>$primary->id,'asset_role'=>$asset->kind === 'image' ? 'thumbnail' : 'subtitles']);
            }
        }
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

    private function normalized(ImportRun $run, array $data, string $root, string $directory, bool $inferDirectory = true): void
    {
        $kind = $data['kind'] ?? 'post';
        if (! in_array($kind, ['video', 'short', 'post', 'poll', 'comment'], true) || empty($data['id'])) return;
        $source = $data['source'] ?? $run->source;
        if (! is_string($source) || strlen($source) > 32) throw new \RuntimeException('Invalid content source.');
        $metadata = $data['metadata'] ?? [];
        $metadata['import_id'] = $run->id; $metadata['raw'] = $data;
        $metadata['media_ids'] = [];
        if (isset($data['files'])) {
            if (! is_array($data['files'])) throw new \RuntimeException('Content files must be a list of relative paths.');
            foreach ($data['files'] as $file) {
                if (! is_string($file)) throw new \RuntimeException('Invalid content file.');
                ImportPath::entry($file);
                $path = ImportPath::resolve($root, $root.'/'.$file);
                $relative = str_replace(DIRECTORY_SEPARATOR,'/',substr($path,strlen(realpath(config('platform.import_inbox_root')))+1));
                $original = \App\Models\MediaOriginal::where('disk','import-inbox')->where('path',$relative)->latest('id')->first();
                $asset = $original ? Media::find($original->media_id) : Media::where('disk','import-inbox')->where('path',$relative)->first();
                if ($asset) $metadata['media_ids'][] = $asset->id;
            }
        } elseif (! in_array($kind,['poll','comment'],true)) {
            $metadata['media_ids'] = $inferDirectory ? $this->assets($directory,$root) : array_values(array_unique([
                ...$this->assets($directory,$root,(string) $data['id']), ...$this->assets($directory,$root,$data['title']??(string) $data['id']),
            ]));
        }
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
                app(ImportProgress::class)->checkpoint($run);
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
