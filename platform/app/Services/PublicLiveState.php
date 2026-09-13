<?php
namespace App\Services;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\DB;
class PublicLiveState
{
    public function heartbeat(SourceRecord $record,string $sessionId): array
    {
        abort_unless(app(PublicContent::class)->forSection('live')->whereKey($record->id)->exists(),404);
        DB::table('public_live_presence')->upsert([['record_id'=>$record->id,'session_hash'=>hash('sha256',$sessionId),'seen_at'=>now()]],['record_id','session_hash'],['seen_at']);
        return ['online'=>DB::table('public_live_presence')->where('record_id',$record->id)->where('seen_at','>',now()->subSeconds(120))->count(),
            'chat'=>app(PublicContent::class)->children($record,'live_chat')->latest('id')->limit(30)->get()->reverse()->values()->map(fn($message)=>[
                'id'=>$message->id,'author'=>is_string($message->metadata['author']??null)?$message->metadata['author']:'—',
                'body'=>$message->body,'time'=>$message->created_at->format('H:i')])->all()];
    }
}
