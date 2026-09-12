<?php

namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Models\Media;
use App\Services\MediaLibrary;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;

class LocalArchiveAdapter implements ImportAdapter
{
    private const SOURCE = 'local-archive';
    private const SUPPORTED_ARCHIVES = ['zip', 'tar', 'gz', 'tgz'];

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
        if (empty($input['path']) || ! is_string($input['path'])) {
            throw new \InvalidArgumentException('Archive path is required.');
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
        $path = trim((string) ($run->source_options['path'] ?? ''));
        if ($path === '') {
            throw new RuntimeException('Archive path is empty.');
        }

        $archive = $this->resolvePath($path);
        $extension = strtolower(pathinfo($archive, PATHINFO_EXTENSION));
        $run->update(['discovered' => 0, 'status' => 'running']);
        $targetProfile = $run->target_profile ?? 'mixed';

        if (! in_array($extension, self::SUPPORTED_ARCHIVES, true)) {
            throw new RuntimeException('Only zip, tar, tgz, or gz archives are supported for local-archive import.');
        }

        $temporaryRoot = $this->extractArchive($run, $archive, $extension);

        try {
            $this->importFolder($run, $temporaryRoot, $targetProfile);
            if (($run->discovered ?? 0) === 0) {
                $run->increment('skipped');
                $run->update(['status' => 'partial', 'notes' => ['Archive has no supported files.']]);
            }
        } finally {
            $this->deleteDirectoryRecursively($temporaryRoot);
        }
    }

    private function resolvePath(string $path): string
    {
        $candidate = str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\\\\/', $path)
            ? $path
            : rtrim($this->inboxRoot, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');

        $real = realpath($candidate);
        $inbox = realpath($this->inboxRoot);

        if (!$real || !$inbox || ! str_starts_with($real, $inbox . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Archive path is outside permitted inbox directory.');
        }
        if (! is_file($real)) {
            throw new RuntimeException('Archive path is not a file.');
        }
        return $real;
    }

    private function extractArchive(ImportRun $run, string $archive, string $extension): string
    {
        $tempDir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'atapin-import-' . $run->id;
        if (! is_dir($tempDir)) {
            if (! mkdir($tempDir, 0777, true) && ! is_dir($tempDir)) {
                throw new RuntimeException('Cannot create temporary import directory.');
            }
        }
        $this->deleteDirectoryContents($tempDir);

        if ($extension === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($archive) !== true) {
                throw new RuntimeException('Cannot open zip archive.');
            }
            $success = $zip->extractTo($tempDir);
            $zip->close();
            if (! $success) {
                throw new RuntimeException('Could not extract zip archive.');
            }
            return $tempDir;
        }

        if ($extension === 'tar') {
            $tar = new PharData($archive);
            $tar->extractTo($tempDir, null, true);
            return $tempDir;
        }

        if ($extension === 'tgz' || $extension === 'gz') {
            $tarPath = (string) preg_replace(
                ['/\\.tar\\.gz$/i', '/\\.tgz$/i', '/\\.gz$/i'],
                '.tar',
                $archive
            );

            if (! is_file($tarPath)) {
                $gz = new PharData($archive);
                $gz->decompress();
            }

            if (! is_file($tarPath) && is_file($archive . '.tar')) {
                $tarPath = $archive . '.tar';
            }

            if (! is_file($tarPath)) {
                throw new RuntimeException('Could not decompress .gz/.tgz archive to TAR.');
            }

            $tar = new PharData($tarPath);
            $tar->extractTo($tempDir, null, true);
            return $tempDir;
        }

        throw new RuntimeException('Unsupported archive type.');
    }

    private function importFolder(ImportRun $run, string $folder, string $targetProfile): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $found = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $found++;
            $run->increment('discovered');
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($folder) + 1);
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
            throw new RuntimeException('Archive is empty.');
        }
    }

    private function registerFile(string $relative, string $sourcePath, ?int $userId, string $targetProfile): Media
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath) ?: 'application/octet-stream';
        $size = filesize($sourcePath);
        if (! $size) {
            throw new RuntimeException('Empty file.');
        }

        $sourceId = 'local-archive:' . hash('sha256', $relative . '|' . $size . '|' . $sourcePath);
        $name = basename($relative);

        return Media::firstOrCreate([
            'source' => self::SOURCE,
            'source_id' => $sourceId,
        ], [
            'title' => mb_substr($name, 0, 255),
            'original_name' => mb_substr($name, 0, 255),
            'kind' => MediaLibrary::kind($mime),
            'mime' => $mime,
            'bytes' => $size,
            'disk' => 'import-inbox',
            'path' => 'archive/' . ltrim($relative, '/\\'),
            'sha256' => hash_file('sha256', $sourcePath),
            'metadata' => [
                'source_path' => $sourcePath,
                'relative_path' => ltrim($relative, '/\\'),
                'target_profile' => $this->normalizeTargetProfile($targetProfile),
            ],
            'status' => 'unsorted',
            'user_id' => $userId,
        ]);
    }

    private function normalizeTargetProfile(?string $profile): string
    {
        return in_array($profile, ['media_library', 'videos', 'posts', 'shorts', 'comments', 'polls'], true)
            ? $profile
            : 'mixed';
    }

    private function deleteDirectoryContents(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $current = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($current)) {
                $this->deleteDirectoryRecursively($current);
            } else {
                @unlink($current);
            }
        }
    }

    private function deleteDirectoryRecursively(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $this->deleteDirectoryContents($path);
        @rmdir($path);
    }
}
