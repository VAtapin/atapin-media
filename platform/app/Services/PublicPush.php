<?php
namespace App\Services;
use App\Models\PublicPushSubscription;
use App\Jobs\SendPublicPush;
use Minishlink\WebPush\{WebPush,Subscription};
class PublicPush
{
    public function __construct(private Settings $settings) {}
    public function ready(): bool {return (bool)$this->settings->get('push_public_key')&&$this->settings->hasSecret('push_private_key');}
    public function validEndpoint(string $url): bool
    {
        $parts=parse_url($url);
        $host=strtolower($parts['host']??'');
        return $parts&&($parts['scheme']??'')==='https'&&!isset($parts['user'],$parts['pass'])&&!isset($parts['fragment'])
            &&!isset($parts['user'])&&!isset($parts['pass'])&&($parts['port']??443)===443
            &&(in_array($host,['fcm.googleapis.com','updates.push.services.mozilla.com','push.services.mozilla.com','web.push.apple.com'],true)||preg_match('/^[a-z0-9-]+\.notify\.windows\.com$/D',$host)===1);
    }
    public function dispatchDue(): int
    {
        if(!$this->ready())return 0;$count=0;
        PublicPushSubscription::chunkById(100,function($subscriptions)use(&$count){foreach($subscriptions as $subscription){
            $record=app(PublicContent::class)->forSection('live')->find($subscription->record_id);
            $time=$record?app(PublicLiveReminders::class)->time($record):null;
            if(!$time||!$time->isFuture()||$time->gt(now()->addMinutes(15))||$subscription->sent_for===$time->timestamp||$subscription->failed_for===$time->timestamp)continue;
            SendPublicPush::dispatch($subscription->id,$time->timestamp);$count++;
        }});return $count;
    }
    public function send(array $subscription,string $payload): \Minishlink\WebPush\MessageSentReport
    {
        if(!$this->ready()||!$this->validEndpoint($subscription['endpoint']??''))throw new \RuntimeException('Push configuration unavailable.');
        $push=new WebPush(['VAPID'=>['subject'=>config('app.url'),'publicKey'=>$this->settings->get('push_public_key'),'privateKey'=>$this->settings->secret('push_private_key')]],['TTL'=>900],new \GuzzleHttp\Client(['timeout'=>10,'connect_timeout'=>5,'allow_redirects'=>false]));
        return $push->sendOneNotification(Subscription::create($subscription),$payload);
    }
}
