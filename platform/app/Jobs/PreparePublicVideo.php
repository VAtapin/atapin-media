<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\PublicVideoOptimizer;
use Illuminate\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;

class PreparePublicVideo implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable, SerializesModels;

    public int $timeout = 21600;
    public int $tries = 1;
    public int $uniqueFor = 86400;

    public function __construct(public string $mediaId) {}
    public function uniqueId(): string { return $this->mediaId; }

    public function handle(PublicVideoOptimizer $optimizer): void
    {
        $media = Media::find($this->mediaId);
        if ($media) $optimizer->prepare($media);
    }
}
