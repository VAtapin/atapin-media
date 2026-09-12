<?php

namespace App\Services\Importing;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class ServerImportBrowser
{
    public function list(string $path = ''): array
    {
        $configured = config('platform.import_inbox_root');
        if (! is_dir($configured)) return ['data' => [], 'path' => '', 'parent' => null, 'available' => false, 'truncated' => false];
        try {
            $directory = ImportPath::resolve($configured, $path ?: '.');
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['path' => __('imports.browser_invalid')]);
        }
        if (! is_dir($directory)) throw ValidationException::withMessages(['path' => __('imports.browser_invalid')]);
        $base = realpath($configured);
        $relative = $directory === $base ? '' : str_replace(DIRECTORY_SEPARATOR, '/', substr($directory, strlen($base) + 1));
        $items = []; $truncated = false;
        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isDot() || $file->isLink() || str_starts_with($file->getFilename(), '.')) continue;
            if (count($items) >= 500) { $truncated = true; break; }
            $archive = ! $file->isDir() && preg_match('/\.(zip|tar|tgz|tar\.gz)$/i', $file->getFilename());
            $items[] = ['name' => $file->getFilename(), 'path' => ($relative === '' ? '' : $relative.'/').$file->getFilename(),
                'source' => $file->isDir() ? 'local-folder' : ($archive ? 'local-archive' : null),
                'type' => $file->isDir() ? 'folder' : ($archive ? 'archive' : 'file'), 'bytes' => $file->isDir() ? null : $file->getSize()];
        }
        usort($items, fn ($a, $b) => ($a['type'] !== 'folder') <=> ($b['type'] !== 'folder') ?: strnatcasecmp($a['name'], $b['name']));
        return ['data' => $items, 'path' => $relative, 'parent' => $relative === '' ? null : (str_contains($relative, '/') ? substr($relative, 0, strrpos($relative, '/')) : ''),
            'available' => true, 'truncated' => $truncated];
    }
}
