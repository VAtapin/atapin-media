<?php

namespace App\Services;

use App\Models\Media;
use App\Models\ResumableMediaUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResumableMediaUploadService
{
    public const CHUNK_SIZE = 4 * 1024 * 1024;

    private const STAGING_PREFIX = 'media-uploads';

    public function start(array $data, int $user): ResumableMediaUpload
    {
        $payload = validator($data, [
            'request_key' => 'required|uuid',
            'name' => 'required|string|max:255',
            'size' => 'required|integer|min:1|max:' . config('platform.media_upload_max_bytes'),
        ])->validate();

        $name = $this->normalizeOriginalName($payload['name']);
        $id = (string) Str::uuid();
        $size = (int) $payload['size'];

        return DB::transaction(function () use ($payload, $name, $size, $user, $id): ResumableMediaUpload {
            $existing = ResumableMediaUpload::query()
                ->where('request_key', $payload['request_key'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ($existing->user_id !== $user || $existing->bytes !== $size || $existing->original_name !== $name) {
                    abort(409, 'The upload key is already used.');
                }
                return $existing;
            }

            $this->assertStorageCapacity($size);

            return ResumableMediaUpload::create([
                'id' => $id,
                'user_id' => $user,
                'request_key' => $payload['request_key'],
                'original_name' => $name,
                'bytes' => $size,
                'disk' => $this->disk(),
                'staging_path' => self::STAGING_PREFIX . '/' . $id . '.part',
            ]);
        });
    }

    public function append(ResumableMediaUpload $upload, int $offset, string $body, string $sha): ResumableMediaUpload
    {
        $length = strlen($body);
        if ($offset < 0 || $length === 0 || $length > self::CHUNK_SIZE || $offset + $length > $upload->bytes) {
            abort(413, 'Invalid chunk size.');
        }

        $sha = strtolower(trim($sha));
        if (!preg_match('/^[a-f0-9]{64}$/', $sha) || !hash_equals(hash('sha256', $body), $sha)) {
            abort(422, 'Checksum mismatch.');
        }

        $lock = $this->uploadLock($upload->id);
        try {
            $current = ResumableMediaUpload::lockForUpdate()->findOrFail($upload->id);
            if ($current->status !== ResumableMediaUpload::STATUS_UPLOADING || $offset !== $current->offset) {
                return $current;
            }

            if ($this->chunkExists($current->id, $offset, $length, $sha)) {
                return $current->fresh();
            }

            $this->assertStorageCapacity($length);

            $path = $this->path($current->staging_path);
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Storage unavailable.');
            }

            $file = fopen($path, 'c+b');
            if (!$file) {
                throw new \RuntimeException('Storage unavailable.');
            }

            if (!flock($file, LOCK_EX | LOCK_NB)) {
                fclose($file);
                abort(409, 'Upload file is currently busy.');
            }

            try {
                $actual = fstat($file)['size'];
                if ($actual < $offset) {
                    throw new \RuntimeException('Upload state is inconsistent.');
                }

                if (!ftruncate($file, $offset) || fseek($file, $offset) !== 0) {
                    throw new \RuntimeException('Storage unavailable.');
                }

                $written = 0;
                while ($written < $length) {
                    $chunk = substr($body, $written, 8192);
                    $count = fwrite($file, $chunk);
                    if ($count === false || $count === 0) {
                        throw new \RuntimeException('Storage unavailable.');
                    }
                    $written += $count;
                }

                if (!fflush($file) || (function_exists('fsync') && !fsync($file))) {
                    throw new \RuntimeException('Storage unavailable.');
                }

                DB::transaction(function () use ($current, $offset, $length, $sha): void {
                    $current = ResumableMediaUpload::lockForUpdate()->findOrFail($current->id);
                    if ($current->status !== ResumableMediaUpload::STATUS_UPLOADING || $current->offset !== $offset) {
                        return;
                    }
                    DB::table('resumable_media_upload_chunks')->insert([
                        'upload_id' => $current->id,
                        'offset' => $offset,
                        'bytes' => $length,
                        'sha256' => $sha,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $current->update(['offset' => $offset + $length]);
                });
            } finally {
                fflush($file);
                flock($file, LOCK_UN);
                fclose($file);
            }

            return $current->fresh();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function finish(ResumableMediaUpload $upload): Media
    {
        $lock = $this->uploadLock($upload->id);
        try { return $this->finalize($upload); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function finalize(ResumableMediaUpload $upload): Media
    {
        $upload = $upload->fresh();

        if ($upload->status === ResumableMediaUpload::STATUS_COMPLETE) {
            $original=\App\Models\MediaOriginal::where('source','upload')->where('source_id',$upload->id)->first();
            $media = $original ? Media::find($original->media_id) : Media::where('source', 'upload')->where('source_id', $upload->id)->first();
            if (!$media) {
                abort(500, 'Upload finished without media record.');
            }
            return $media;
        }

        if ($upload->status !== ResumableMediaUpload::STATUS_UPLOADING) {
            abort(409, 'Upload is not in progress.');
        }

        if ($upload->offset !== $upload->bytes) {
            abort(409, 'Upload is incomplete.');
        }

        $source = $this->path($upload->staging_path);
        if (!is_file($source) || filesize($source) !== (int) $upload->bytes) {
            abort(409, 'Upload file is not complete.');
        }

        $handle = fopen($source, 'rb');
        if (!$handle) {
            abort(503, 'Storage unavailable.');
        }
        $sample = fread($handle, 262_144) ?: '';
        fclose($handle);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($sample) ?: 'application/octet-stream';

        DB::transaction(function () use ($upload, $mime): void {
            $upload = ResumableMediaUpload::lockForUpdate()->findOrFail($upload->id);
            if ($upload->status === ResumableMediaUpload::STATUS_COMPLETE) {
                return;
            }
            if ($upload->status !== ResumableMediaUpload::STATUS_UPLOADING || $upload->offset !== $upload->bytes) {
                abort(409, 'Upload is not ready to be finalized.');
            }
            $upload->update([
                'status' => ResumableMediaUpload::STATUS_FINALIZING,
                'mime' => $mime,
            ]);
        });

        $destination = $this->nextMediaPath();
        $destinationPath = $this->path($destination);

        if (!Storage::disk($this->disk())->makeDirectory(dirname($destination))) {
            abort(503, 'Storage unavailable.');
        }
        if (!rename($source, $destinationPath)) {
            $upload->update(['status' => ResumableMediaUpload::STATUS_UPLOADING]);
            abort(503, 'Storage unavailable.');
        }

        try {
            return DB::transaction(function () use ($upload, $destination, $mime): Media {
                $media = app(\App\Services\Importing\ImportedMediaRegistry::class)->register([
                    'id' => (string) Str::uuid(),
                    'title' => $upload->original_name,
                    'original_name' => $upload->original_name,
                    'kind' => MediaLibrary::kind($mime),
                    'mime' => $mime,
                    'bytes' => $upload->bytes,
                    'disk' => $upload->disk,
                    'path' => $destination,
                    'sha256' => hash_file('sha256', $this->path($destination)),
                    'status' => 'unsorted',
                    'source' => 'upload',
                    'source_id' => $upload->id,
                    'user_id' => $upload->user_id,
                ]);

                ResumableMediaUpload::lockForUpdate()->findOrFail($upload->id)->update([
                    'status' => ResumableMediaUpload::STATUS_COMPLETE,
                    'final_path' => $destination,
                ]);
                DB::table('resumable_media_upload_chunks')->where('upload_id', $upload->id)->delete();

                app(Audit::class)->record('media.uploaded', $media->id);
                return $media;
            });
        } catch (\Throwable $error) {
            if (Storage::disk($this->disk())->exists($destination)) {
                rename($destinationPath, $source);
            }
            ResumableMediaUpload::where('id', $upload->id)->update([
                'status' => ResumableMediaUpload::STATUS_UPLOADING,
                'final_path' => null,
            ]);
            throw $error;
        }
    }

    protected function assertStorageCapacity(int $additionalBytes): void
    {
        $root = $this->path('');
        $available = @disk_free_space($root);
        if ($available === false) {
            abort(503, 'Storage unavailable.');
        }
        $activeUploads = (int) ResumableMediaUpload::query()
            ->where('status', ResumableMediaUpload::STATUS_UPLOADING)
            ->count();
        if ($activeUploads > (int) config('platform.media_upload_max_pending_uploads')) {
            abort(429, 'Too many active uploads.');
        }
        $reserved = (int) DB::table('resumable_media_uploads')
            ->where('status', '!=', ResumableMediaUpload::STATUS_COMPLETE)
            ->sum(DB::raw('(bytes - offset)'));

        if ($available - $reserved - $additionalBytes < config('platform.media_upload_reserve_free_bytes')) {
            abort(507, 'Not enough free storage.');
        }
        $total = (int) DB::table('resumable_media_uploads')
            ->where('status', '!=', ResumableMediaUpload::STATUS_COMPLETE)
            ->sum('bytes') + $additionalBytes;
        if ($total > config('platform.media_upload_max_archive_bytes')) {
            abort(507, 'Media upload quota exceeded.');
        }
    }

    protected function chunkExists(string $uploadId, int $offset, int $bytes, string $sha): bool
    {
        $existing = DB::table('resumable_media_upload_chunks')
            ->where('upload_id', $uploadId)
            ->where('offset', $offset)
            ->first();
        if (!$existing) {
            return false;
        }
        if ((int) $existing->bytes !== $bytes) {
            abort(409, 'Chunk already exists with a different size.');
        }
        if (!hash_equals((string) $existing->sha256, $sha)) {
            abort(409, 'Chunk already exists with a different checksum.');
        }
        return true;
    }

    protected function uploadLock(string $uploadId)
    {
        $lockPath = $this->path('.lock/' . $uploadId . '.lock');
        $directory = dirname($lockPath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Storage unavailable.');
        }
        $lock = fopen($lockPath, 'c+b');
        if (!$lock) {
            throw new \RuntimeException('Storage unavailable.');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            abort(409, 'Upload is busy.');
        }
        return $lock;
    }

    protected function disk(): string
    {
        return config('platform.media_disk');
    }

    protected function path(string $relative): string
    {
        $disk = Storage::disk($this->disk());
        if (!method_exists($disk, 'path')) {
            abort(500, 'Unsupported storage disk for resumable uploads.');
        }
        return $disk->path($relative);
    }

    protected function nextMediaPath(): string
    {
        $id = (string) Str::uuid();
        return 'originals/' . now()->format('Y/m') . '/' . $id;
    }

    protected function normalizeOriginalName(string $name): string
    {
        $name = trim(basename(str_replace('\\', '/', $name)));
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? $name;
        $name = preg_replace('/[\/:*?"<>|]/', '_', $name) ?? $name;
        $name = trim($name, " .\t\n\r\0\x0B");
        if ($name === '') {
            $name = 'file';
        }
        return mb_substr($name, 0, 255);
    }
}
