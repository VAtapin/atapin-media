<?php
namespace App\Services;
use App\Models\{SourceRecord,User};
use Illuminate\Support\Facades\DB;
class PublicLiveState
{
    public function heartbeat(SourceRecord $record,string $sessionId,?User $user=null): array
    {
        abort_unless(app(PublicContent::class)->forSection('live')->whereKey($record->id)->exists(),404);
        DB::table('public_live_presence')->upsert([['record_id'=>$record->id,'session_hash'=>hash('sha256',$sessionId),'seen_at'=>now()]],['record_id','session_hash'],['seen_at']);
        return ['online'=>DB::table('public_live_presence')->where('record_id',$record->id)->where('seen_at','>',now()->subSeconds(120))->count(),
            'chat'=>app(PublicContent::class)->childrenForViewer($record,'live_chat',$user,$sessionId)->latest('id')->limit(30)->get()->reverse()->values()->map(function($message)use($user,$sessionId){
                $metadata=$message->metadata??[];$own=app(PublicContent::class)->isOwnedByViewer($message,$user,$sessionId);return [
                'id'=>$message->id,'author'=>is_string($message->metadata['author']??null)?$message->metadata['author']:'—',
                'body'=>$message->body,'time'=>$message->created_at->format('H:i'),'own'=>$own,
                'blocked'=>$own&&(bool)($metadata['moderation']['blocked']??false),'pending'=>$own&&($metadata['moderation']['state']??null)==='pending_ai'];})->all()];
    }
}
