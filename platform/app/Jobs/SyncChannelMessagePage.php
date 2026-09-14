<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};
use Illuminate\Foundation\Queue\Queueable;
class SyncChannelMessagePage implements ShouldQueue,ShouldBeUnique
{
    use Queueable;
    public int $tries=1;
    public int $timeout=90;
    public int $uniqueFor=120;
    public function __construct(public int $pageId){}
    public function uniqueId():string{return 'channel-message-page:'.$this->pageId;}
    public function handle(\App\Services\Publishing\YouTubeCommentSync $sync):void{$sync->page($this->pageId);}
    public function failed(?\Throwable $error):void{app(\App\Services\Publishing\YouTubeCommentSync::class)->fail($this->pageId);}
}
