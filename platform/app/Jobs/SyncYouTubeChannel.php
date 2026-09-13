<?php

namespace App\Jobs;

use App\Services\Publishing\YouTubeInboundSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;

class SyncYouTubeChannel implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function uniqueId(): string
    {
        return 'youtube-channel-sync';
    }

    public function handle(YouTubeInboundSync $sync): void
    {
        $sync->run();
    }
}
