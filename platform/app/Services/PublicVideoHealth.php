<?php

namespace App\Services;

use App\Models\Media;
use App\Services\Importing\LocalMediaLinks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicVideoHealth
{
    public function report(): array
    {
        $failed = DB::table('failed_jobs')->where('payload', 'like', '%PreparePublicVideo%')->count();
        $stuck = DB::table('jobs')->where('payload', 'like', '%PreparePublicVideo%')
            ->where('created_at', '<', now()->subMinutes(30))->count();
        $missing = $this->missingOptimizations();
        $issues = [];

        if ($failed > 0) $issues[] = __('ui.public_video_health_failed', ['count' => $failed]);
        if ($stuck > 0) $issues[] = __('ui.public_video_health_stuck', ['count' => $stuck]);
        if ($missing > 0) $issues[] = __('ui.public_video_health_missing', ['count' => $missing]);

        return ['ok' => $issues === [], 'issues' => $issues, 'failed' => $failed, 'stuck' => $stuck,
            'missing' => $missing, 'checked_at' => now()->toIso8601String()];
    }

    private function missingOptimizations(): int
    {
        $links = app(LocalMediaLinks::class);
        $locator = app(MediaOriginalLocator::class);
        $ids = app(PublicContent::class)->forSection('videos')->get()
            ->flatMap(fn ($record) => $links->ids($record))->unique()->values();
        if ($ids->isEmpty()) return 0;
        return Media::whereIn('id', $ids)->where('kind', 'video')->get()
            ->filter(fn (Media $media) => !$this->hasPreparedVariant($media)
                && !$this->hasPendingJob($media->id) && $locator->find($media) !== null)->count();
    }

    private function hasPreparedVariant(Media $media): bool
    {
        $variant = $media->metadata['public_video'] ?? null;
        return is_array($variant) && isset($variant['disk'], $variant['path'])
            && Storage::disk($variant['disk'])->exists($variant['path']);
    }

    private function hasPendingJob(string $mediaId): bool
    {
        return DB::table('jobs')->where('payload', 'like', '%PreparePublicVideo%')
            ->where('payload', 'like', '%'.$mediaId.'%')->exists();
    }
}
