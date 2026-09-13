<?php
namespace App\Jobs;
use App\Models\PublicPushSubscription;
use App\Services\{PublicPush,PublicContent,PublicLiveReminders};
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};
class SendPublicPush implements ShouldQueue,ShouldBeUnique
{
    use Queueable;
    public int $tries=3;public int $backoff=60;public int $timeout=30;public int $uniqueFor=1200;
    public function __construct(public int $subscriptionId,public int $startsAt) {}
    public function uniqueId(): string {return $this->subscriptionId.':'.$this->startsAt;}
    public function handle(PublicPush $push,PublicContent $content,PublicLiveReminders $reminders): void
    {
        $subscription=PublicPushSubscription::find($this->subscriptionId);
        if(!$subscription||$subscription->sent_for===$this->startsAt)return;
        $record=$content->forSection('live')->find($subscription->record_id);$time=$record?$reminders->time($record):null;
        if(!$time||!$time->isFuture()||$time->timestamp!==$this->startsAt)return;
        $report=$push->send($subscription->subscription,json_encode(['title'=>$record->title,'body'=>$time->format('d.m.Y H:i'),'url'=>'/live?event='.$record->id,'tag'=>'live-'.$record->id.'-'.$this->startsAt],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        if($report->isSubscriptionExpired()){$subscription->delete();return;}
        if(!$report->isSuccess())throw new \RuntimeException('Push service rejected notification.');
        $subscription->update(['sent_for'=>$this->startsAt,'failed_for'=>null]);
    }
    public function failed(?\Throwable $error): void {PublicPushSubscription::whereKey($this->subscriptionId)->update(['failed_for'=>$this->startsAt]);}
}
