<?php

namespace App\Jobs;

use App\Services\Publishing\PublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;

class PublishToPlatform implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $publicationId)
    {
    }

    public function uniqueId(): string
    {
        return 'publication:'.$this->publicationId;
    }

    public function handle(PublishingService $publishing): void
    {
        $publishing->execute($this->publicationId);
    }
}
