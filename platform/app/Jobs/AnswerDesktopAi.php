<?php

namespace App\Jobs;

use App\Models\DesktopAiRequest;
use App\Services\DesktopAi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnswerDesktopAi implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public int $timeout = 110;
    public function __construct(public int $requestId) {}
    public function handle(DesktopAi $service): void
    {
        if (!DesktopAiRequest::whereKey($this->requestId)->where('status','queued')->update(['status'=>'processing'])) return;
        $service->complete(DesktopAiRequest::findOrFail($this->requestId));
    }
    public function failed(?\Throwable $error): void { DesktopAiRequest::whereKey($this->requestId)->whereIn('status',['queued','processing'])->update(['status'=>'failed']); }
}
