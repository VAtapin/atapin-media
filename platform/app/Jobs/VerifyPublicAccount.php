<?php
namespace App\Jobs;
use App\Models\User;
use App\Services\PublicLiveReminders;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeEncrypted};
use Illuminate\Support\Facades\{Mail,URL};
class VerifyPublicAccount implements ShouldQueue,ShouldBeEncrypted {
    use Queueable;
    public int $tries=3,$backoff=60,$timeout=30;
    public function __construct(public int $userId,public string $email,public string $locale){}
    public function handle(PublicLiveReminders $mail): void {
        $user=User::find($this->userId);if(!$user||$user->email!==$this->email||$user->email_verified_at)return;
        if(!$mail->mailReady())throw new \RuntimeException('Email transport is not configured.');
        $link=URL::temporarySignedRoute('public.account-verify',now()->addDays(2),['user'=>$user->id,'hash'=>sha1($user->email)]);
        Mail::raw(__('public.account_verify_mail',['link'=>$link],$this->locale),fn($message)=>$message->to($user->email)->subject(__('public.account_verify',[],$this->locale)));
    }
}
