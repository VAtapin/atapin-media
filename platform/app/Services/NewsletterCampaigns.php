<?php
namespace App\Services;
use App\Models\{NewsletterCampaign,NewsletterSubscription,NewsletterDelivery};
use App\Jobs\DeliverNewsletter;
use Illuminate\Support\Facades\DB;

class NewsletterCampaigns
{
    public function send(NewsletterCampaign $campaign): void
    {
        abort_unless(app(PublicLiveReminders::class)->mailReady(),422,__('workspaces.mail_not_ready'));
        DB::transaction(function()use($campaign){
            $campaign=NewsletterCampaign::lockForUpdate()->findOrFail($campaign->id);
            abort_unless(in_array($campaign->status,['draft','scheduled'],true),409);
            abort_unless($campaign->user && $campaign->user->hasPermission('subscribers.manage'),403);
            $campaign->update(['status'=>'preparing']);
            \App\Jobs\PrepareNewsletterCampaign::dispatch($campaign->id)->afterCommit();
            app(Audit::class)->record('newsletter.send_started',(string)$campaign->id);
        });
    }
    public function prepare(NewsletterCampaign $campaign): void
    {
        abort_unless($campaign->user?->hasPermission('subscribers.manage')&&app(PublicLiveReminders::class)->mailReady(),403);
        if($campaign->status==='sending'){$this->fanout($campaign);return;}
        if($campaign->status!=='preparing')return;
        NewsletterSubscription::where('status','active')->whereNotNull('confirmed_at')->where('confirmed_at','<=',$campaign->updated_at)
            ->when($campaign->locale,fn($q,$s)=>$q->where('locale',$s))->when($campaign->segment,fn($q,$s)=>$q->whereJsonContains('tags',$s))
            ->chunkById(200,function($rows)use($campaign){
                if($campaign->fresh()->status!=='preparing')return false;
                foreach($rows as $row)NewsletterDelivery::firstOrCreate(['newsletter_campaign_id'=>$campaign->id,'newsletter_subscription_id'=>$row->id],['status'=>'pending']);
            });
        if(!NewsletterCampaign::whereKey($campaign->id)->where('status','preparing')->update(['status'=>'sending']))return;
        $this->fanout($campaign->fresh());
        if(!$campaign->deliveries()->exists())NewsletterCampaign::whereKey($campaign->id)->where('status','sending')->update(['status'=>'sent']);
    }
    private function fanout(NewsletterCampaign $campaign): void
    {
        if($campaign->status!=='sending'||!$campaign->user?->hasPermission('subscribers.manage')||!app(PublicLiveReminders::class)->mailReady())return;
        $campaign->deliveries()->where('status','pending')->chunkById(200,function($rows)use($campaign){foreach($rows as $row){
            if($campaign->fresh()->status!=='sending')return false;
            if(!NewsletterDelivery::whereKey($row->id)->where('status','pending')->update(['status'=>'queued','updated_at'=>now()]))continue;
            try{DeliverNewsletter::dispatch($row->id)->afterCommit();}catch(\Throwable){NewsletterDelivery::whereKey($row->id)->where('status','queued')->update(['status'=>'pending']);}
        }});
    }
    public function dispatchDue(): int
    {
        NewsletterDelivery::where('status','sending')->where('updated_at','<',now()->subMinutes(15))->update(['status'=>'unknown']);
        NewsletterCampaign::where('status','sending')->chunkById(100,function($rows){foreach($rows as $row){if($row->deliveries()->where('status','pending')->exists()&&$row->updated_at->lt(now()->subMinutes(5))&&NewsletterCampaign::whereKey($row->id)->where('status','sending')->where('updated_at','<',now()->subMinutes(5))->update(['updated_at'=>now()]))\App\Jobs\PrepareNewsletterCampaign::dispatch($row->id)->afterCommit();if(!$row->deliveries()->whereIn('status',['pending','queued','sending'])->exists())NewsletterCampaign::whereKey($row->id)->where('status','sending')->update(['status'=>$row->deliveries()->whereIn('status',['failed','unknown'])->exists()?'partial':'sent']);}});
        NewsletterDelivery::where('status','queued')->where('updated_at','<',now()->subMinutes(10))->whereHas('campaign',fn($q)=>$q->where('status','sending'))->limit(100)->get()->each(function($row){if(NewsletterDelivery::whereKey($row->id)->where('status','queued')->where('updated_at','<',now()->subMinutes(10))->update(['updated_at'=>now()]))DeliverNewsletter::dispatch($row->id)->afterCommit();});
        $count=0;
        if(!app(PublicLiveReminders::class)->mailReady())return 0;
        NewsletterCampaign::where('status','scheduled')->where('send_at','<=',now())->chunkById(50,function($rows)use(&$count){foreach($rows as $row){try{$this->send($row);$count++;}catch(\Throwable){NewsletterCampaign::whereKey($row->id)->where('status','scheduled')->update(['status'=>'failed']);}}});
        return $count;
    }
}
