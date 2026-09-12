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
        $disk = config('platform.media_disk'); $id = (string) Str::uuid();
        $path = $file->storeAs('originals/'.now()->format('Y/m'), $id, $disk);
        if (!$path) throw new \RuntimeException('Media storage failed.');
        try {
            return DB::transaction(function () use ($file, $user, $disk, $id, $path) {
                $mime = $file->getMimeType() ?: 'application/octet-stream';
                $name = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
                $media = Media::create(['id' => $id, 'title' => $name, 'original_name' => $name,
                    'kind' => self::kind($mime), 'mime' => $mime, 'bytes' => $file->getSize(), 'disk' => $disk,
                    'path' => $path, 'sha256' => hash_file('sha256', $file->getRealPath()), 'status' => 'unsorted', 'user_id' => $user,
                    'source' => 'upload', 'source_id' => $id]);
                app(Audit::class)->record('media.uploaded', $media->id);
                return $media;
            });
        } catch (\Throwable $error) { Storage::disk($disk)->delete($path); throw $error; }
    }
}
