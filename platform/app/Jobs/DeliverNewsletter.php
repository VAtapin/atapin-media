<?php
namespace App\Jobs;
use App\Models\NewsletterDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{Mail,URL};
class DeliverNewsletter implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public function __construct(public int $id){}
    public function handle(): void
    {
        if(!NewsletterDelivery::whereKey($this->id)->where('status','queued')->update(['status'=>'sending']))return;
        $delivery=NewsletterDelivery::with('campaign','subscription')->findOrFail($this->id);$campaign=$delivery->campaign;$subscriber=$delivery->subscription;
        $attempted=false;$locale=app()->getLocale();
        try{
            if($campaign->status!=='sending'||!$campaign->user?->hasPermission('subscribers.manage')||$subscriber->status!=='active'||!$subscriber->confirmed_at){$delivery->update(['status'=>'skipped']);return;}
            $cancel=URL::signedRoute('public.newsletter-optout',['subscription'=>$subscriber->id]);
            if(!app(\App\Services\PublicLiveReminders::class)->mailReady())throw new \RuntimeException('mail unavailable');
            app()->setLocale($subscriber->locale);$attempted=true;
            Mail::send('mail.newsletter-campaign',['campaign'=>$campaign,'cancel'=>$cancel],function($mail)use($campaign,$subscriber){$mail->to($subscriber->email)->subject($campaign->subject);});
            $delivery->update(['status'=>'sent','sent_at'=>now()]);
        }catch(\Throwable){$delivery->update(['status'=>$attempted?'unknown':'failed']);}
        finally{app()->setLocale($locale);$this->finish($campaign);}
    }
    private function finish($campaign): void
    {
        if(!$campaign->deliveries()->whereIn('status',['pending','queued','sending'])->exists())\App\Models\NewsletterCampaign::whereKey($campaign->id)->where('status','sending')->update(['status'=>$campaign->deliveries()->whereIn('status',['failed','unknown'])->exists()?'partial':'sent']);
    }
    public function failed(?\Throwable $error): void
    {
        if(($delivery=NewsletterDelivery::find($this->id))&&in_array($delivery->status,['queued','sending'],true)){$delivery->update(['status'=>'unknown']);$this->finish($delivery->campaign);}
    }
}
