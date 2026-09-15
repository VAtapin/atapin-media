<?php
namespace App\Services;
use App\Models\{SourceRecord,User,NewsletterSubscription};
use Illuminate\Support\Carbon;
class Polls
{
    public function open(SourceRecord $record): bool
    {
        $poll=$record->metadata['poll']??[];
        try {
            return ($poll['active']??true) && (empty($poll['starts_at'])||Carbon::parse($poll['starts_at'])->lte(now())) && (empty($poll['ends_at'])||Carbon::parse($poll['ends_at'])->gt(now()));
        } catch (\Throwable) { return false; }
    }
    public function allowed(SourceRecord $record,?User $user): bool
    {
        if (!$user) return false;
        return ($record->metadata['poll']['audience']??'registered')!=='subscriber' || NewsletterSubscription::where('email',$user->email)->where('status','active')->whereNotNull('confirmed_at')->whereNotNull('consented_at')->exists();
    }
    public function results(SourceRecord $record,?User $user): bool
    {
        return match($record->metadata['poll']['results']??'always') {
            'after_vote'=>$user && app(PublicParticipation::class)->states($record)->where('user_id',$user->id)->where('action','vote')->exists(),
            'after_close'=>$this->closed($record), 'hidden'=>false, default=>true,
        };
    }
    public function current(string $placement): ?SourceRecord
    {
        $records=app(PublicContent::class)->forSection('community')->where('kind','poll')->latest()->limit(100)->get()->filter(fn($record)=>($record->metadata['poll']['active']??true)&&in_array($placement,$record->metadata['poll']['placements']??['community','live','buecher'],true));
        return $records->first(fn($record)=>$this->open($record))??$records->first(fn($record)=>$this->closed($record)&&in_array($record->metadata['poll']['results']??'always',['always','after_close'],true));
    }
    private function closed(SourceRecord $record): bool {try{$ends=$record->metadata['poll']['ends_at']??null;return $ends&&Carbon::parse($ends)->lte(now());}catch(\Throwable){return false;}}
}
