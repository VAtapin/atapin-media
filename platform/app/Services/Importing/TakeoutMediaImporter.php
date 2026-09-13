<?php
namespace App\Services\Importing;

use App\Models\{ImportRun, Media};
use App\Services\{CanonicalMediaStorage, MediaLibrary, MediaOriginalLocator};
use Illuminate\Support\Facades\DB;

class TakeoutMediaImporter
{
    private array $prepared = [];

    public function prepare(ImportRun $run, array $file): void
    {
        if (isset($this->prepared[$file['path']])) return;
        $key = 'takeout-file:v3:'.$file['path'];
        $signature = ['bytes' => filesize($file['path']), 'mtime' => filemtime($file['path'])];
        $item = app(ImportJournal::class)->item($run, $key);
        if (app(ImportJournal::class)->done($run, $key, $signature) && ($media = Media::find($item?->subject_id)) && app(MediaOriginalLocator::class)->find($media)) return;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['path']) ?: 'application/octet-stream';
        $progress = app(ImportProgress::class);
        $hash = $progress->hashFile($run, $file['path'], 'verify_file');
        $progress->checkpoint($run, 'files', ['file' => $hash, 'file_bytes' => 0, 'file_total_bytes' => $signature['bytes']], true);
        $stored = app(CanonicalMediaStorage::class)->storePath($file['path'], $mime, pathinfo($file['entry'], PATHINFO_EXTENSION), $hash,
            fn(int $bytes, int $total) => $progress->checkpoint($run, null, ['file_bytes' => $bytes, 'file_total_bytes' => $total]));
        $this->prepared[$file['path']] = compact('stored', 'mime');
    }

    public function file(ImportRun $run, array $file, ?string $title = null, ?string $role = null): Media
    {
        $journal = app(ImportJournal::class);
        $key = 'takeout-file:v3:'.$file['path'];
        $signature = ['bytes' => filesize($file['path']), 'mtime' => filemtime($file['path'])];
        $item = $journal->item($run, $key);
        if ($journal->done($run, $key, $signature) && ($media = Media::find($item?->subject_id)) && app(MediaOriginalLocator::class)->find($media)) return $media;

        $this->prepare($run, $file);
        ['stored' => $stored, 'mime' => $mime] = $this->prepared[$file['path']];

        return DB::transaction(function () use ($run, $stored, $mime, $title, $role, $journal, $key, $signature, $item) {
            $media = app(ImportedMediaRegistry::class)->register([
                'source' => 'youtube-takeout', 'source_id' => $stored['sha256'],
                'title' => mb_substr($title ?: $stored['filename'], 0, 255), 'original_name' => $stored['filename'],
                'kind' => MediaLibrary::kind($mime), 'mime' => $mime, 'bytes' => $stored['bytes'],
                'disk' => $stored['disk'], 'path' => $stored['path'], 'sha256' => $stored['sha256'],
                'status' => 'unsorted', 'user_id' => $run->user_id, 'asset_role' => $role,
                'metadata' => ['import_id' => $run->id, 'target_profile' => $run->target_profile ?: 'mixed'],
            ]);
            if (! $item) {
                $run->increment('discovered');
                $run->increment($media->wasRecentlyCreated ? 'imported' : 'skipped');
            }
            $journal->record($run, $key, $stored['filename'], 'file', $media->wasRecentlyCreated ? 'added' : 'duplicate', $media->id, ['signature' => $signature]);
            return $media;
        });
    }
}
