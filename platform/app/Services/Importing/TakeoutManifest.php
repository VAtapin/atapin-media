<?php
namespace App\Services\Importing;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/** Read Google's archive browser as data. Never render or execute its HTML/scripts. */
class TakeoutManifest
{
    public function read(string $path): array
    {
        if (str_ends_with(strtolower($path), '.zip')) {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) throw new RuntimeException('Cannot open Takeout report.');
            try {
                $index = $zip->locateName('Takeout/archive_browser.html');
                if ($index === false) $index = $zip->locateName('archive_browser.html');
                if ($index === false) return [];
                $stat = $zip->statIndex($index);
                if ($stat['size'] > 16 * 1024 * 1024) throw new RuntimeException('Takeout report is too large.');
                $html = $zip->getFromIndex($index);
            } finally { $zip->close(); }
        } else {
            if (filesize($path) > 16 * 1024 * 1024) throw new RuntimeException('Takeout report is too large.');
            $html = file_get_contents($path);
        }
        if (!is_string($html) || $html === '') return [];
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($html, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new DOMXPath($document);
        $service = $xpath->query('//*[@id="service-details-YOUTUBE"]')->item(0);
        if (!$service) return [];
        $name = $xpath->query('.//div[@class="service_name"]/h1', $service)->item(0)?->textContent;
        if (!$name) return [];
        $paths = [];
        foreach ($xpath->query('.//div[@class="file-leaf"]', $service) as $leaf) {
            $filename = $xpath->query('./div[@class="extracted-file-name"]', $leaf)->item(0)?->textContent;
            if ($filename === null) continue;
            // Google inserts one presentation space, not part of the exported filename.
            if (str_starts_with($filename, ' ')) $filename = substr($filename, 1);
            $folders = [];
            for ($parent = $leaf->parentNode; $parent && $parent !== $service; $parent = $parent->parentNode) {
                if (!$parent instanceof DOMElement) continue;
                $folder = $xpath->query('./button[contains(concat(" ", @class, " "), " extracted-folder ")]//div[@class="extracted-folder-name"]', $parent)->item(0);
                if ($folder) array_unshift($folders, $folder->textContent);
            }
            foreach ([$name, ...$folders, $filename] as $component) {
                if ($component === '' || str_contains($component, '/') || str_contains($component, '\\')) throw new RuntimeException('Unsafe Takeout report filename.');
                ImportPath::entry($component);
            }
            $paths[] = ImportPath::entry('Takeout/'.$name.'/'.implode('/', [...$folders, $filename]));
            if (count($paths) > 100000) throw new RuntimeException('Takeout manifest limit exceeded.');
        }
        return array_values(array_unique($paths));
    }

    public function entry(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $position = strpos('/'.$path, '/Takeout/');
        return $position === false ? $path : substr($path, $position);
    }
}
