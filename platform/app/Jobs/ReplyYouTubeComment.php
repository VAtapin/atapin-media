<?php
namespace App\Jobs;
use App\Models\{SourceRecord,User};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class ReplyYouTubeComment implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public function __construct(public int $id,public int $user,public string $body){}
    public function handle(\App\Services\Publishing\YouTubeClient $youtube): void
    {
        $record=\Illuminate\Support\Facades\DB::transaction(function(){$record=SourceRecord::lockForUpdate()->find($this->id);if(!$record||($record->metadata['inbox_reply']['status']??'')!=='queued')return null;$record->updateQuietly(['metadata'=>[...($record->metadata??[]),'inbox_reply'=>['status'=>'sending']]]);return $record;});if(!$record)return;
        try{abort_unless(User::find($this->user)?->hasPermission('community.moderate')&&$record->source==='youtube'&&$record->kind==='comment',403);$record->updateQuietly(['metadata'=>[...($record->metadata??[]),'inbox_reply'=>['status'=>'sending']]]);$id=(string)($record->metadata['parent_comment_id']??$record->metadata['raw']['id']??$record->metadata['comment_id']??\Illuminate\Support\Str::afterLast($record->source_id,':'));abort_unless(preg_match('/^[A-Za-z0-9_.-]{5,200}$/D',$id),422);$result=$youtube->replyComment($id,$this->body);$record=$record->fresh();$record->updateQuietly(['metadata'=>[...($record->metadata??[]),'inbox_reply'=>['status'=>'sent','external_id'=>$result['id']??null]]]);}
        catch(\Throwable){$record->updateQuietly(['metadata'=>[...($record->metadata??[]),'inbox_reply'=>['status'=>'failed']]]);}
    }
    public function failed(?\Throwable $error): void{if($record=SourceRecord::find($this->id))$record->updateQuietly(['metadata'=>[...($record->metadata??[]),'inbox_reply'=>['status'=>'unknown']]]);}
}
