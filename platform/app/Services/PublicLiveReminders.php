<?php
namespace App\Services;
use App\Models\{PublicContentState,SourceRecord};
use App\Jobs\SendPublicLiveReminder;
use Illuminate\Support\Carbon;
class PublicLiveReminders
{
    public function mailReady(): bool {return config('mail.default')==='smtp'&&!empty(config('mail.mailers.smtp.host'));}
    public function time(SourceRecord $record): ?Carbon
    {
        if(($record->metadata['live_status']??null)!=='scheduled'||!is_string($record->metadata['starts_at']??null))return null;
        try{return Carbon::parse($record->metadata['starts_at'],config('platform.timezone'));}catch(\Throwable){return null;}
    }
    public function dispatchDue(): int
    {
        if(!$this->mailReady())return 0;
        $count=0;
        PublicContentState::where('subject_type','record')->where('action','reminder')->where('value->enabled',true)->chunkById(100,function($states)use(&$count){
            foreach($states as $state){
                $record=app(PublicContent::class)->forSection('live')->find($state->subject_id);
                $time=$record?$this->time($record):null;
                if(!$time||$time->isPast()||$time->gt(now()->addMinutes(15))||($state->value['sent_for']??null)===$time->timestamp||($state->value['failed_for']??null)===$time->timestamp)continue;
                dispatch(new SendPublicLiveReminder($state->id,$time->timestamp));$count++;
            }
        });
        return $count;
    }
}
