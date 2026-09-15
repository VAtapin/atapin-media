<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class LiveRecordingRemuxer
{
    /**
     * Repackage a MediaMTX fMP4 recording as a seekable MP4 without re-encoding.
     * The original path is returned when remuxing is unavailable so a recording
     * is never lost only because the optional post-processing step failed.
     */
    public function prepare(string $source): array
    {
        if (! is_file($source)) {
            throw new \RuntimeException('Live recording is unavailable.');
        }

        $temporary = tempnam(dirname($source), '.live-remux-');
        if ($temporary === false || ! rename($temporary, $temporary.'.mp4')) {
            if (is_file($temporary)) @unlink($temporary);
            throw new \RuntimeException('Live recording remux storage is unavailable.');
        }
        $output = $temporary.'.mp4';
        $failure = null;

        try {
            $result = Process::timeout(1800)->run([
                (string) config('platform.media_ffmpeg_binary', 'ffmpeg'),
                '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
                '-i', $source, '-map', '0', '-c', 'copy', '-movflags', '+faststart', $output,
            ]);

            if ($result->successful() && is_file($output) && (int) filesize($output) > 0) {
                return ['path' => $output, 'status' => 'remuxed'];
            }
            $failure = new \RuntimeException('FFmpeg did not produce a remuxed recording.');
        } catch (\Throwable $error) {
            $failure = $error;
        }

        @unlink($output);
        if ((bool) config('platform.live_recording_remux_fallback', true)) {
            return ['path' => $source, 'status' => 'fallback'];
        }
        throw new \RuntimeException('Live recording remux failed.', 0, $failure);
    }
}
