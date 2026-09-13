<?php
namespace App\Jobs;
use App\Models\PublicAiChatRequest;
use App\Services\PublicAiChat;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
class AnswerPublicAiChat implements ShouldQueue
{
    use Queueable;
    public int $tries=1;public int $timeout=50;
    public function __construct(public int $requestId) {}
    public function handle(PublicAiChat $service): void
    {
        $request=PublicAiChatRequest::find($this->requestId);if(!$request)return;
        if(!PublicAiChatRequest::whereKey($request->id)->where('status','queued')->update(['status'=>'processing']))return;
        try{$answer=$service->answer($request);$request->update(['status'=>'completed','answer'=>$answer]);}
        catch(\Throwable){$request->update(['status'=>'failed']);}
    }
    public function failed(?\Throwable $error): void {PublicAiChatRequest::whereKey($this->requestId)->where('status','!=','completed')->update(['status'=>'failed']);}
}
