<?php
namespace App\Jobs;
use App\Models\ContactMessage;
use App\Services\{Settings,PublicLiveReminders};
use App\Mail\ContactEnquiryMail;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};
use Illuminate\Support\Facades\Mail;
class ForwardContactMessage implements ShouldQueue,ShouldBeUnique
{
    use Queueable;
    public int $tries=3;
    public int $timeout=30;
    public int $backoff=60;
    public int $uniqueFor=1200;
    public function __construct(public int $messageId) {}
    public function uniqueId(): string {return (string)$this->messageId;}
    public function handle(Settings $settings,PublicLiveReminders $mail): void
    {
        $message=ContactMessage::find($this->messageId);
        if(!$message||$message->delivered_at)return;
        $recipient=$settings->get('contact_email');
        if(!is_string($recipient)||!filter_var($recipient,FILTER_VALIDATE_EMAIL)||!$mail->mailReady()){
            $message->update(['delivery_status'=>'configuration_required']);return;
        }
        Mail::to($recipient)->send(new ContactEnquiryMail($message));
        $message->update(['delivery_status'=>'sent','delivered_at'=>now()]);
    }
    public function failed(?\Throwable $error): void
    {
        ContactMessage::whereKey($this->messageId)->whereNull('delivered_at')->update(['delivery_status'=>'failed']);
    }
}
