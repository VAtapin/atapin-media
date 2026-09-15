<?php
namespace App\Services;

use App\Models\{PublicContentState,SourceRecord,Product,User};

class PublicParticipation
{
    public function states($subject)
    {
        return PublicContentState::where('subject_type',$subject instanceof Product?'book':'record')->where('subject_id',$subject->id);
    }
    public function mine($subject,?User $user): array
    {
        return $user&&!$user->isStaffAccount()?$this->states($subject)->where('user_id',$user->id)->pluck('value','action')->all():[];
    }
    public function options(SourceRecord $poll): array
    {
        return array_values(array_filter($poll->metadata['poll']['options']??[],fn($option)=>is_array($option)&&is_string($option['text']??null)));
    }
    public function voteResults(SourceRecord $poll): array
    {
        $counts=array_fill(0,count($this->options($poll)),0);$total=0;
        // Aggregate identical ballots, then decode JSON once per distinct selection.
        // This also supports legacy single-choice values on both SQLite and MySQL.
        foreach($this->states($poll)->where('action','vote')->select('value')->selectRaw('COUNT(*) AS votes')->groupBy('value')->cursor() as $ballot){
            $total+=(int)$ballot->votes;
            $selected=$ballot->value['options']??[$ballot->value['option']??null];
            if(!is_array($selected))continue;
            foreach(array_unique($selected,SORT_REGULAR) as $choice){
                if((is_int($choice)||(is_string($choice)&&ctype_digit($choice)))&&array_key_exists((int)$choice,$counts))$counts[(int)$choice]+=(int)$ballot->votes;
            }
        }
        $results=[];foreach($this->options($poll) as $index=>$option)$results[]=['text'=>$option['text'],'count'=>$counts[$index],'percent'=>$total?round($counts[$index]/$total*100,1):0];
        return ['votes'=>$total,'results'=>$results];
    }
    public function save(User $user,$subject,array $data): void
    {
        if(($data['action']??null)==='vote'&&$subject instanceof SourceRecord){
            \Illuminate\Support\Facades\DB::transaction(function()use($user,$subject,$data){
                $poll=SourceRecord::lockForUpdate()->findOrFail($subject->id);
                abort_unless(app(PublicContent::class)->visible($poll),404);
                $this->persist($user,$poll,$data);
            });
            return;
        }
        $this->persist($user,$subject,$data);
    }
    private function persist(User $user,$subject,array $data): void
    {
        $action=$data['action'];
        // Staff accounts keep their public account separate, but an authenticated
        // editor or administrator may still take part in an explicitly published poll.
        abort_unless(!$user->isStaffAccount()||$action==='vote',403);
        abort_unless(in_array($action,$subject instanceof Product?['bookmark','progress']:['bookmark','like','reminder','progress','vote']),422);
        if($action==='reminder')abort_unless(app(PublicContent::class)->section($subject)==='live',422);
        if($action==='vote'){
            abort_unless($subject->kind==='poll',422);
            abort_if(!empty($subject->metadata['poll']['external_url']),422);
            abort_unless(app(Polls::class)->open($subject),422,__('public.poll_closed'));
            abort_unless(app(Polls::class)->allowed($subject,$user),403);
            $selected=array_values(array_unique(array_map('intval',$data['options']??[$data['option']??-1])));
            abort_unless(count($selected)>0 && (($subject->metadata['poll']['multiple']??false)||count($selected)===1),422);
            foreach($selected as $option)abort_unless(array_key_exists($option,$this->options($subject)),422);
            $value=['option'=>$selected[0],'options'=>$selected];
        }elseif($action==='progress'){
            abort_if($subject instanceof Product&&($data['position']??0)>100,422);
            $value=['position'=>(int)($data['position']??0)];
        }
        else $value=['enabled'=>(bool)($data['enabled']??true)];
        if($action==='reminder'){
            $time=app(PublicLiveReminders::class)->time($subject);
            if($value['enabled'])abort_unless($time&&$time->isFuture()&&app(PublicLiveReminders::class)->mailReady(),422,__('public.reminder_unavailable'));
            $previous=$this->states($subject)->where('user_id',$user->id)->where('action','reminder')->first()?->value??[];
            $value=[...$previous,...$value,'locale'=>app()->getLocale(),'channel'=>'email'];
            if($value['enabled']&&!($previous['enabled']??false))unset($value['failed_for'],$value['delivery']);
        }
        PublicContentState::updateOrCreate(['user_id'=>$user->id,'subject_type'=>$subject instanceof Product?'book':'record','subject_id'=>$subject->id,'action'=>$action],['value'=>$value]);
    }
    public function comment(User $user,SourceRecord $parent,string $body,string $kind='comment',?string $sessionId=null): void
    {
        app(PublicCommunitySubmission::class)->message($user,$parent,$body,$kind,$sessionId);
    }
}
