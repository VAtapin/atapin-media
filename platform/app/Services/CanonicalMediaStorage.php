<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\MimeTypes;

class CanonicalMediaStorage
{
    public const DISK = 'media-canonical';

    private const PUBLIC_TYPES = [
        'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
        'video/x-matroska' => 'mkv', 'video/x-msvideo' => 'avi',
        'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/flac' => 'flac',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'text/vtt' => 'vtt', 'application/x-subrip' => 'srt', 'text/x-ssa' => 'ass',
    ];

    public function storeUploaded(UploadedFile $file, ?string $mime = null): array
    {
        return $this->storePath($file->getRealPath(), $mime ?: ($file->getMimeType() ?: 'application/octet-stream'), $file->getClientOriginalExtension());
    }

    /** The source is retained until the caller has committed its database record. */
    public function storePath(string $source, string $mime, ?string $extension = null, ?string $sha256 = null, ?callable $progress = null): array
    {
        if (! is_file($source)) throw new \RuntimeException('Media source is unavailable.');
        $sha256 ??= hash_file('sha256', $source);
        if (! preg_match('/^[a-f0-9]{64}$/D', $sha256)) throw new \RuntimeException('Invalid media checksum.');
        $extension = strtolower($extension ?? pathinfo($source, PATHINFO_EXTENSION));
        $subtitle = $mime === 'text/plain' && in_array($extension, ['srt', 'vtt', 'ass'], true);
        $public = isset(self::PUBLIC_TYPES[$mime]) || $subtitle;
        $extension = $subtitle ? $extension : (self::PUBLIC_TYPES[$mime] ?? (MimeTypes::getDefault()->getExtensions($mime)[0] ?? 'bin'));
        $filename = $sha256.'.'.$extension;
        $diskName = $public ? self::DISK : 'local';
        $path = $public ? $filename : 'media-files/'.$filename;
        $disk = Storage::disk($diskName);
        $destination = $disk->path($path);
        $directory = dirname($destination);
        $bytes = (int) filesize($source);

        if (! is_dir($directory)) {
            if (! mkdir($directory, $public ? 0755 : 0700, true) && ! is_dir($directory)) throw new \RuntimeException('Media storage is unavailable.');
            // Plesk workers use umask 0077; nginx must be able to traverse the public directory.
            if ($public && ! chmod($directory, 0755)) throw new \RuntimeException('Cannot enable public media access.');
        }
        if (! is_file($destination)) {
            $free = disk_free_space($directory);
            if ($free === false || $free - $bytes < (int) config('platform.media_upload_reserve_free_bytes')) {
                throw new \RuntimeException('Not enough free media storage.');
            }
            $temporary = $directory.'/.'.bin2hex(random_bytes(16)).'.part';
            $input = fopen($source, 'rb');
            $output = fopen($temporary, 'xb');
            if (! $input || ! $output) {
                if ($input) fclose($input);
                if ($output) fclose($output);
                @unlink($temporary);
                throw new \RuntimeException('Media storage is unavailable.');
            }
            try {
                $copied = 0;
                while (! feof($input)) {
                    $chunk = fread($input, 8 * 1024 * 1024);
                    if ($chunk === false) throw new \RuntimeException('Cannot read media source.');
                    $offset = 0;
                    while ($offset < strlen($chunk)) {
                        $written = fwrite($output, substr($chunk, $offset));
                        if (! $written) throw new \RuntimeException('Cannot write media file.');
                        $offset += $written;
                    }
                    $copied += strlen($chunk);
                    if ($progress) $progress($copied, $bytes);
                }
                if ($copied !== $bytes || ! fflush($output)) throw new \RuntimeException('Incomplete media copy.');
                fclose($input); $input = null;
                fclose($output); $output = null;
                if (! chmod($temporary, $public ? 0644 : 0600)) throw new \RuntimeException('Cannot set media permissions.');
                if (! rename($temporary, $destination)) throw new \RuntimeException('Cannot finalize media file.');
            } finally {
                if (is_resource($input)) fclose($input);
                if (is_resource($output)) fclose($output);
                if (is_file($temporary)) unlink($temporary);
            }
        }
        if ((int) filesize($destination) !== $bytes) throw new \RuntimeException('Stored media size differs from its source.');
        return ['disk' => $diskName, 'path' => $path, 'filename' => $filename, 'sha256' => $sha256, 'bytes' => $bytes];
    }

    public function url(Media $media): ?string
    {
        if ($media->disk !== self::DISK || ! preg_match('/^[a-f0-9]{64}\.(mp4|webm|mov|mkv|avi|mp3|ogg|m4a|wav|flac|jpg|png|webp|gif|srt|vtt|ass)$/D', $media->path)) return null;
        return Storage::disk(self::DISK)->url($media->path);
    }
}
