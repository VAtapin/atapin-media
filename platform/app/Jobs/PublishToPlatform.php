<?php

namespace App\Jobs;

use App\Services\Publishing\PublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUniqueUntilProcessing, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;

class PublishToPlatform implements ShouldQueue, ShouldBeUniqueUntilProcessing
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

    public function failed(?\Throwable $error): void
    {
        app(PublishingService::class)->fail($this->publicationId, $error ?? new \RuntimeException('Publication worker stopped.'));
    }
}
