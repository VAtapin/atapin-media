<?php

namespace App\Services\Publishing;

use App\Models\{Media, SourceRecord};
use App\Services\{CanonicalMediaStorage, MediaOriginalLocator};
use App\Services\Importing\ImportedMediaRegistry;
use Illuminate\Support\Facades\{Cache, Storage};
use Symfony\Component\Process\Process;

class VideoRenderer
{
    public function video(SourceRecord $record, ?string $provider = null): array
    {
        $resolver = app(MediaResolver::class);
        if ($video = $resolver->video($record)) return $video;
        $image = $resolver->image($record,$provider);
        $audio = $resolver->assets($record)->first(fn (Media $media) => $media->kind === 'audio');
        $signature = hash('sha256', json_encode([$record->title, $record->body, $image['media']->id ?? null, $image['media']->sha256 ?? null, $audio?->id, $audio?->sha256], JSON_THROW_ON_ERROR));
        return Cache::lock('publishing-render:'.$signature, 3600)->block(30, function () use ($record, $image, $audio, $signature) {
            if ($media = Media::where('source', 'publishing-render')->where('source_id', $signature)->first()) {
                $location = app(MediaOriginalLocator::class)->find($media);
                if ($location) return ['media' => $media, 'path' => app(MediaOriginalLocator::class)->path($location)];
            }
            $directory = 'publishing-render/'.$signature;
            Storage::disk('local')->makeDirectory($directory);
            $root = Storage::disk('local')->path($directory);
            $path = $root.'/video.mp4';
            $command = [(string) config('publishing.live_relay_ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y'];
            if ($image) $command = [...$command, '-loop', '1', '-i', $image['path']];
            else $command = [...$command, '-f', 'lavfi', '-i', 'color=c=black:s=1280x720:r=25'];
            $filter = 'scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,setsar=1';
            if (! $image) {
                $text = implode("\n", mb_str_split(trim($record->title."\n\n".strip_tags((string) $record->body)), 52));
                Storage::disk('local')->put($directory.'/caption.txt', $text);
                // A controlled relative text file avoids treating user text or Windows paths as filter syntax.
                $filter .= ",drawtext=textfile=caption.txt:expansion=none:fontcolor=white:fontsize=30:x=40:y='40-max(0,text_h-h+80)*t/15'";
            }
            if ($audio) {
                $location = app(MediaOriginalLocator::class)->find($audio);
                $command = [...$command, '-i', app(MediaOriginalLocator::class)->path($location), '-shortest', '-c:a', 'aac', '-b:a', '192k'];
            } else $command = [...$command, '-t', '15'];
            $command = [...$command, '-vf', $filter, '-c:v', 'libx264', '-preset', 'veryfast', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', $path];
            try {
                $process = $this->process($command, $root);
                $process->setTimeout(3500);
                $process->disableOutput();
                $process->run();
                if (! $process->isSuccessful() || ! is_file($path)) throw new \RuntimeException('Video adaptation failed. Check ffmpeg, libx264 and drawtext support.');
                $stored = app(CanonicalMediaStorage::class)->storePath($path, 'video/mp4', 'mp4');
                $media = app(ImportedMediaRegistry::class)->register([
                    'source' => 'publishing-render', 'source_id' => $signature, 'title' => $record->title,
                    'disk' => $stored['disk'], 'path' => $stored['path'], 'sha256' => $stored['sha256'], 'bytes' => $stored['bytes'],
                    'kind' => 'video', 'mime' => 'video/mp4', 'original_name' => $stored['filename'], 'status' => 'ready',
                    'metadata' => ['derived_from_record' => $record->id, 'library_only' => true],
                ]);
                return ['media' => $media, 'path' => Storage::disk($stored['disk'])->path($stored['path'])];
            } finally { Storage::disk('local')->deleteDirectory($directory); }
        });
    }

    protected function process(array $command, string $root): Process { return new Process($command, $root); }
}
