<?php
namespace App\Services;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class MediaLibrary
{
    public static function kind(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image', str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio', $mime === 'application/pdf' => 'pdf',
            str_starts_with($mime, 'text/') || str_contains($mime, 'document') => 'document', default => 'other',
        };
    }
    public function upload(UploadedFile $file, int $user): Media
    {
        $id = (string) Str::uuid();
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $stored = app(CanonicalMediaStorage::class)->storeUploaded($file, $mime);
        $disk = $stored['disk']; $path = $stored['path'];
        try {
            return DB::transaction(function () use ($file, $user, $disk, $id, $path, $mime, $stored) {
                $name = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
                $media = app(\App\Services\Importing\ImportedMediaRegistry::class)->register(['id' => $id, 'title' => $name, 'original_name' => $stored['filename'],
                    'kind' => self::kind($mime), 'mime' => $mime, 'bytes' => $file->getSize(), 'disk' => $disk,
                    'path' => $path, 'sha256' => $stored['sha256'], 'status' => 'unsorted', 'user_id' => $user,
                    'source' => 'upload', 'source_id' => $id]);
                app(Audit::class)->record('media.uploaded', $media->id);
                return $media;
            });
        } catch (\Throwable $error) { throw $error; }
    }
}
