<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Models\Media;
use App\Services\MediaLibrary;
use Illuminate\Validation\ValidationException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class LocalFolderAdapter implements ImportAdapter
{
    public function __construct(private ?string $inboxRoot = null)
    {
        $this->inboxRoot ??= config('platform.import_inbox_root');
    }
    public function source(): string { return 'local-folder'; }
    public function validate(array $input): array
    {
        if (empty($input['path']) || ! is_string($input['path'])) {
            throw ValidationException::withMessages(['path' => __('imports.path_required')]);
        }
        return $input;
    }
    public function normalize(array $input): array
    {
        return ['source' => $this->source(), 'source_kind' => $this->source(), 'source_ref' => $input['path'],
            'source_options' => ['path' => $input['path']], 'target_profile' => $input['target_profile'] ?? 'mixed'];
    }
    public function import(ImportRun $run): void
    {
        $root = ImportPath::resolve($this->inboxRoot, $run->source_options['path'] ?? '');
        if (! is_dir($root)) throw new \RuntimeException('Import source is not a folder.');
        $this->importDirectory($run, $root);
    }
    public function importDirectory(ImportRun $run, string $root): void
    {
        $inbox = realpath($this->inboxRoot);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) continue;
            if (str_starts_with($file->getFilename(), '.') || preg_match('/\.(part|ytdl|tmp)$/i', $file->getFilename())) continue;
            $absolute = ImportPath::resolve($root, $file->getPathname());
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolute, strlen($inbox) + 1));
            $run->increment('discovered');
            try {
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute) ?: 'application/octet-stream';
                $hash = hash_file('sha256', $absolute);
                $media = Media::firstOrCreate(['source' => $run->source, 'source_id' => hash('sha256', $relative.'|'.$hash)], [
                    'title' => mb_substr($file->getFilename(), 0, 255), 'original_name' => mb_substr($file->getFilename(), 0, 255),
                    'kind' => MediaLibrary::kind($mime), 'mime' => $mime, 'bytes' => $file->getSize(),
                    'disk' => 'import-inbox', 'path' => $relative, 'sha256' => $hash, 'status' => 'unsorted',
                    'user_id' => $run->user_id, 'metadata' => ['relative_path' => $relative, 'import_id' => $run->id,
                        'target_profile' => $run->target_profile ?? 'mixed'],
                ]);
                $run->increment($media->wasRecentlyCreated ? 'imported' : 'skipped');
            } catch (\Throwable $error) {
                $notes = $run->fresh()->notes ?? [];
                if (count($notes) < 100) $notes[] = $relative.': '.$error->getMessage();
                $run->update(['notes' => $notes]); $run->increment('skipped');
            }
        }
        if (! $run->fresh()->discovered) $run->update(['notes' => ['No files found.']]);
        app(ContentMetadataImporter::class)->scan($run, $root);
    }
}
