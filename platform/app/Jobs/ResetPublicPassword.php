<?php
namespace App\Jobs;
use App\Models\User;
use App\Services\PublicLiveReminders;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeEncrypted};
use Illuminate\Support\Facades\Mail;
class ResetPublicPassword implements ShouldQueue,ShouldBeEncrypted {
    use Queueable;
    public int $tries=3,$backoff=60,$timeout=30;
    public function __construct(public int $userId,public string $email,public string $token,public string $locale){}
    public function handle(PublicLiveReminders $mail): void {
        $user=User::find($this->userId);if(!$user||$user->email!==$this->email)return;
        if(!\Illuminate\Support\Facades\Password::tokenExists($user,$this->token))return;
        if(!$mail->mailReady())throw new \RuntimeException('Email transport is not configured.');
        $url=route('public.password-reset',['token'=>$this->token,'email'=>$this->email]);
        Mail::raw(__('public.password_reset_mail',['link'=>$url],$this->locale),fn($message)=>$message->to($this->email)->subject(__('public.password_forgot',[],$this->locale)));
    }
}
