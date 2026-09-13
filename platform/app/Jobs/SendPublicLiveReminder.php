<?php
namespace App\Jobs;
use App\Models\{PublicContentState,User};
use App\Services\{PublicContent,PublicLiveReminders};
use App\Mail\PublicLiveReminderMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};
use Illuminate\Support\Facades\{DB,Mail};
class SendPublicLiveReminder implements ShouldQueue,ShouldBeUnique
{
    use Queueable;
    public int $tries=3;
    public int $timeout=30;
    public int $backoff=60;
    public int $uniqueFor=1200;
    public function __construct(public int $stateId,public int $startsAt) {}
    public function uniqueId(): string {return $this->stateId.':'.$this->startsAt;}
    public function handle(PublicContent $content,PublicLiveReminders $reminders): void
    {
        $state=PublicContentState::find($this->stateId);
        if(!$state||$state->subject_type!=='record'||$state->action!=='reminder'||!($state->value['enabled']??false)||($state->value['sent_for']??null)===$this->startsAt)return;
        $record=$content->forSection('live')->find($state->subject_id);$time=$record?$reminders->time($record):null;
        if(!$time||$time->timestamp!==$this->startsAt||$time->isPast())return;
        if(!$reminders->mailReady())throw new \RuntimeException('SMTP mail delivery is not configured.');
        $user=User::find($state->user_id);if(!$user)return;
        $locale=$state->value['locale']??'de';if(!in_array($locale,config('platform.locales'))) $locale='de';
        $mail=(new PublicLiveReminderMail($record->title,$time->copy()->timezone(config('platform.timezone'))->format('d.m.Y H:i T'),$content->card($record)['url']))->locale($locale);
        Mail::to($user->email)->send($mail);
        DB::transaction(function(){
            $current=PublicContentState::lockForUpdate()->find($this->stateId);
            if($current)$current->update(['value'=>[...$current->value,'sent_for'=>$this->startsAt,'sent_at'=>now()->toIso8601String(),'delivery'=>'sent']]);
        });
    }
    public function failed(?\Throwable $error): void
    {
        DB::transaction(function(){
            $state=PublicContentState::lockForUpdate()->find($this->stateId);
            if($state)$state->update(['value'=>[...$state->value,'delivery'=>'failed','failed_for'=>$this->startsAt]]);
        });
    }
}
