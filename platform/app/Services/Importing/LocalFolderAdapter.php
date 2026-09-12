<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Models\Media;
use App\Services\MediaLibrary;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class LocalFolderAdapter implements ImportAdapter
{
    private const SOURCE = 'local-folder';

    public function __construct(private ?string $inboxRoot = null)
    {
        $this->inboxRoot = $inboxRoot ?? config('platform.import_inbox_root');
    }

    public function source(): string
    {
        return self::SOURCE;
    }

    public function validate(array $input): array
    {
        if (empty($input['path']) || !is_string($input['path'])) {
            throw new \InvalidArgumentException('Path is required for local folder import.');
        }
        return $input;
    }

    public function normalize(array $input): array
    {
        return [
            'source' => self::SOURCE,
            'source_kind' => self::SOURCE,
            'source_ref' => $input['path'],
            'source_options' => ['path' => $input['path']],
            'target_profile' => $input['target_profile'] ?? 'mixed',
        ];
    }

    public function import(ImportRun $run): void
    {
        $path = trim((string)($run->source_options['path'] ?? ''));
        if ($path === '') {
            throw new \InvalidArgumentException('Source path is empty.');
        }

        $root = $this->resolvePath($path);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $found = 0;
        $targetProfile = $run->target_profile ?? 'mixed';
        $run->update(['discovered' => 0]);

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr(str_replace('\\','/',$file->getPathname()), strlen($root)+1);
            $found++;
            $run->increment('discovered');
            try {
                $media = $this->registerFile($relative, $file->getPathname(), $run->user_id, $targetProfile);
                $run->increment($media->wasRecentlyCreated ? 'imported' : 'skipped');
            } catch (\Throwable $error) {
                $notes = is_array($run->notes) ? $run->notes : [];
                if (count($notes) < 150) {
                    $notes[] = sprintf('%s: %s', $relative, $error->getMessage());
                }
                $run->update(['notes' => $notes]);
                $run->increment('skipped');
            }
        }

        if ($found === 0) {
            $run->increment('skipped');
            $run->update(['status' => 'partial', 'notes' => ['No files found in source folder.']]);
        }
    }

    private function resolvePath(string $path): string
    {
        $candidate = str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\\/', $path)
            ? $path
            : rtrim($this->inboxRoot, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');

        $real = realpath($candidate);
        $inbox = realpath($this->inboxRoot);
        if (!$real || !$inbox || !str_starts_with($real, $inbox . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Source path is outside permitted inbox directory.');
        }
        if (!is_dir($real)) {
            throw new \RuntimeException('Source path is not a folder.');
        }
        return $real;
    }

    private function registerFile(string $relative, string $sourcePath, ?int $userId, string $targetProfile): Media
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath) ?: 'application/octet-stream';
        $size = filesize($sourcePath);
        $sourceId = 'local-folder:' . hash('sha256', $relative . '|' . $size . '|' . $sourcePath);
        $name = basename($relative);

        if ($size === 0) {
            throw new \RuntimeException('Empty file.');
        }

        return Media::firstOrCreate([
            'source' => 'local-folder',
            'source_id' => $sourceId,
        ], [
            'title' => mb_substr($name, 0, 255),
            'original_name' => mb_substr($name, 0, 255),
            'kind' => MediaLibrary::kind($mime),
            'mime' => $mime,
            'bytes' => $size,
            'disk' => 'import-inbox',
            'path' => 'items/' . ltrim($relative, '/\\'),
            'sha256' => hash_file('sha256', $sourcePath),
            'metadata' => [
            'target_profile' => $this->runProfileFromRef($targetProfile),
                'source_path' => $sourcePath,
                'relative_path' => ltrim($relative, '/\\'),
            ],
            'status' => 'unsorted',
            'user_id' => $userId,
        ]);
    }

    private function runProfileFromRef(?string $targetProfile): string
    {
        return match ($targetProfile) {
            'media_library' => 'media_library',
            'videos' => 'videos',
            'posts' => 'posts',
            'shorts' => 'shorts',
            'comments' => 'comments',
            'polls' => 'polls',
            default => 'mixed',
        };
    }
}
