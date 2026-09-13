<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CanonicalMediaStorage
{
    public const DISK = 'media-canonical';

    /** @return array{disk:string,path:string,filename:string,sha256:string,bytes:int} */
    public function storeUploaded(UploadedFile $file, ?string $mime = null): array
    {
        return $this->storePath($file->getRealPath(), $mime ?: ($file->getMimeType() ?: 'application/octet-stream'));
    }

    /** @return array{disk:string,path:string,filename:string,sha256:string,bytes:int} */
    public function storePath(string $source, string $mime, bool $deleteSource = false): array
    {
        if (! is_file($source)) {
            throw new \RuntimeException('Media source is unavailable.');
        }

        $sha256 = hash_file('sha256', $source);
        $filename = $sha256.'.'.$this->extension($mime);
        $path = 'objects/'.$filename;
        $disk = Storage::disk(self::DISK);
        $destination = $disk->path($path);
        $directory = dirname($destination);

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Canonical media storage is unavailable.');
        }

        if (! is_file($destination)) {
            $temporary = $destination.'.'.bin2hex(random_bytes(8)).'.part';
            if (! copy($source, $temporary) || ! rename($temporary, $destination)) {
                @unlink($temporary);
                throw new \RuntimeException('Canonical media storage failed.');
            }
        }

        if ($deleteSource && realpath($source) !== realpath($destination)) {
            @unlink($source);
        }

        return [
            'disk' => self::DISK,
            'path' => $path,
            'filename' => $filename,
            'sha256' => $sha256,
            'bytes' => (int) filesize($destination),
        ];
    }

    private function extension(string $mime): string
    {
        return match (strtolower($mime)) {
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/vtt' => 'vtt',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
