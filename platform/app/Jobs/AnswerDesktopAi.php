<?php

namespace App\Jobs;

use App\Models\{DesktopAiRequest, User};
use App\Services\{ContentStructureReview, DesktopAi};
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
        $entry = DesktopAiRequest::findOrFail($this->requestId);
        try {
            if (!User::find($entry->user_id)?->hasPermission('content.edit')) throw new \RuntimeException('Permission revoked.');
            if ($entry->product_id && !User::find($entry->user_id)?->hasPermission('shop.manage')) throw new \RuntimeException('Book permission revoked.');
            $result = $service->answer($entry);
            $usage = $result['_usage'] ?? [];
            unset($result['_usage']);
            $entry->update(['status'=>'completed','answer'=>mb_substr($result['answer'],0,10000),'proposal'=>$result]);
            app(\App\Services\AiUsage::class)->record($entry, is_array($usage) ? $usage : []);
            if ($entry->purpose === 'structure') app(ContentStructureReview::class)->apply($entry);
        } catch (\Throwable) {
            if ($entry->purpose === 'structure') app(ContentStructureReview::class)->markFailed($entry);
            $entry->update(['status'=>'failed']);
        }
    }
    public function failed(?\Throwable $error): void { DesktopAiRequest::whereKey($this->requestId)->whereIn('status',['queued','processing'])->update(['status'=>'failed']); }
}
