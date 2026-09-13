<?php

namespace App\Services;

use App\Models\Media;

class PublicMediaLinks
{
    public function url(Media $media): ?string
    {
        if ($media->disk !== CanonicalMediaStorage::DISK || ! in_array($media->kind, ['video', 'short', 'image'], true)) {
            return null;
        }

        $source = app(MediaOriginalLocator::class)->find($media);
        if (! $source || $source['disk'] !== CanonicalMediaStorage::DISK) {
            return null;
        }

        $filename = basename($source['path']);
        $link = public_path('media-videos/'.$filename);
        $directory = dirname($link);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            return null;
        }

        $target = app(MediaOriginalLocator::class)->path($source);
        if (is_link($link) && readlink($link) !== $target) {
            @unlink($link);
        }
        if (! file_exists($link) && ! is_link($link) && ! @symlink($target, $link)) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/media-videos/'.rawurlencode($filename);
    }
}
