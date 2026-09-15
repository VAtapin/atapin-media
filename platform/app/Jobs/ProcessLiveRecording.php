<?php

namespace App\Jobs;

use App\Services\PublicBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessLiveRecording implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 3;
    public int $uniqueFor = 7200;

    public function __construct(public string $path, public string $file) {}

    public function uniqueId(): string
    {
        return hash('sha256', $this->path.'|'.$this->file);
    }

    public function handle(PublicBroadcast $broadcast): void
    {
        $broadcast->recording($this->path, $this->file);
    }
}
