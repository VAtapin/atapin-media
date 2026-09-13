<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class PublicVideoOptimizer
{
    public function location(Media $media): ?array
    {
        $variant = $media->metadata['public_video'] ?? null;
        if (is_array($variant) && isset($variant['disk'], $variant['path'])) {
            $disk = Storage::disk($variant['disk']);
            if ($disk->exists($variant['path'])) return ['disk' => $variant['disk'], 'path' => $variant['path']];
        }
        return app(MediaOriginalLocator::class)->find($media);
    }

    public function prepare(Media $media): bool
    {
        if ($media->kind !== 'video') return false;
        $source = app(MediaOriginalLocator::class)->find($media);
        if (!$source || config('filesystems.disks.'.$source['disk'].'.driver') !== 'local') return false;
        $sourcePath = app(MediaOriginalLocator::class)->path($source);
        $root = Storage::disk($source['disk'])->path('');
        $relative = 'web-videos/'.($media->sha256 ?: hash('sha256', $source['disk'].'|'.$source['path'])).'.mp4';
        $targetPath = rtrim($root,'/\\').DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
        if (is_file($targetPath) && filesize($targetPath) > 0) {
            $this->remember($media, $source, $relative, filesize($targetPath));
            return true;
        }
        if (!is_dir(dirname($targetPath))) mkdir(dirname($targetPath),0700,true);
        $tmp = $targetPath.'.'.bin2hex(random_bytes(8)).'.part';
        try {
            $copy = new Process([config('platform.media_ffmpeg_binary'),'-hide_banner','-loglevel','error','-y','-i',$sourcePath,
                '-map','0:v:0','-map','0:a?','-c','copy','-movflags','+faststart',$tmp], timeout: 21600);
            $copy->run();
            if (!$copy->isSuccessful() || !is_file($tmp) || filesize($tmp) < 1) {
                @unlink($tmp);
                $encode = new Process([config('platform.media_ffmpeg_binary'),'-hide_banner','-loglevel','error','-y','-i',$sourcePath,
                    '-map','0:v:0','-map','0:a?','-c:v','libx264','-preset','veryfast','-crf','23','-c:a','aac','-b:a','128k','-movflags','+faststart',$tmp], timeout: 21600);
                $encode->mustRun();
            }
            if (!is_file($tmp) || filesize($tmp) < 1) throw new \RuntimeException('Optimized video is empty.');
            rename($tmp,$targetPath);
            $this->remember($media, $source, $relative, filesize($targetPath));
            return true;
        } finally { if (is_file($tmp)) @unlink($tmp); }
    }

    private function remember(Media $media, array $source, string $relative, int $bytes): void
    {
        $metadata = $media->metadata ?? [];
        $metadata['public_video'] = ['disk'=>$source['disk'],'path'=>$relative,'bytes'=>$bytes,'prepared_at'=>now()->toIso8601String()];
        $media->update(['metadata'=>$metadata]);
    }
}
