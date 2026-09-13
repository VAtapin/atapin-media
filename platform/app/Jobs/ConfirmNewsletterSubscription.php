<?php
namespace App\Jobs;
use App\Models\NewsletterSubscription;
use App\Services\PublicLiveReminders;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\{Mail,URL};
class ConfirmNewsletterSubscription implements ShouldQueue,\Illuminate\Contracts\Queue\ShouldBeEncrypted {
    use Queueable;
    public int $tries=3;
    public int $backoff=60;
    public int $timeout=30;
    public function __construct(public int $subscriptionId, public string $token){}
    public function handle(PublicLiveReminders $mail): void {
        $entry=NewsletterSubscription::find($this->subscriptionId);
        if(!$entry||$entry->status!=='pending'||!hash_equals($entry->token_hash,hash('sha256',$this->token))||$entry->delivery_status==='sent')return;
        if(!$mail->mailReady()){$entry->update(['delivery_status'=>'configuration_required']);return;}
        $confirm=URL::temporarySignedRoute('public.newsletter-confirm',now()->addDays(2),['subscription'=>$entry->id,'token'=>$this->token]);
        $cancel=URL::signedRoute('public.newsletter-cancel',['subscription'=>$entry->id,'token'=>$this->token]);
        Mail::raw(__('public.newsletter_confirm_mail',['confirm'=>$confirm,'cancel'=>$cancel],$entry->locale),function($message)use($entry){$message->to($entry->email)->subject(__('public.newsletter_title',[],$entry->locale));});
        $entry->update(['delivery_status'=>'sent']);
    }
    public function failed(?\Throwable $error): void {NewsletterSubscription::whereKey($this->subscriptionId)->where('status','pending')->where('token_hash',hash('sha256',$this->token))->update(['delivery_status'=>'failed']);}
}
