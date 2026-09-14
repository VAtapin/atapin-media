<?php
namespace App\Services\Publishing;
use App\Models\{ChannelMessageRun,ChannelMessagePage,SourceRecord,Publication,User};
use App\Jobs\SyncChannelMessagePage;
use Illuminate\Support\Facades\{DB,Cache};
use Illuminate\Support\Str;

class YouTubeCommentSync
{
    public function __construct(private readonly YouTubeClient $youtube){}
    public function start(User $user):ChannelMessageRun
    {
        abort_unless($user->hasPermission('community.moderate'),403);
        abort_unless($this->youtube->configured(),422,__('workspaces.disconnected'));
        return Cache::lock('youtube-comment-start',10)->block(3,function()use($user){
            abort_if(ChannelMessageRun::where('provider','youtube')->whereIn('status',['queued','running'])->exists(),409);
            return DB::transaction(function()use($user){$run=ChannelMessageRun::create(['provider'=>'youtube','user_id'=>$user->id]);$page=$run->pages()->create(['page_key'=>hash('sha256','root')]);SyncChannelMessagePage::dispatch($page->id)->afterCommit();return $run;});
        });
    }
    public function retry(ChannelMessageRun $run,User $user):void
    {
        abort_unless($user->hasPermission('community.moderate'),403);abort_unless($this->youtube->configured(),422);
        DB::transaction(function()use($run,$user){$run=ChannelMessageRun::lockForUpdate()->findOrFail($run->id);abort_unless($run->provider==='youtube'&&$run->status==='failed',409);$run->update(['status'=>'queued','error'=>null,'user_id'=>$user->id]);foreach($run->pages()->whereIn('status',['failed','queued'])->get() as $page){$page->update(['status'=>'queued']);SyncChannelMessagePage::dispatch($page->id)->afterCommit();}});
    }
    public function page(int $id):void
    {
        $page=DB::transaction(function()use($id){$candidate=ChannelMessagePage::find($id);if(!$candidate)return null;$run=ChannelMessageRun::lockForUpdate()->find($candidate->run_id);$page=ChannelMessagePage::lockForUpdate()->find($id);if(!$page||$page->status!=='queued'||!$run||!in_array($run->status,['queued','running'],true))return null;$page->update(['status'=>'processing']);$run->update(['status'=>'running']);return $page;});
        if(!$page)return;
        try{
            $run=$page->run;abort_unless(User::find($run->user_id)?->hasPermission('community.moderate')&&$this->youtube->configured(),403);
            $channel=$this->youtube->channel()['id']??null;abort_unless(is_string($channel)&&preg_match('/^UC[A-Za-z0-9_-]{22}$/D',$channel),422);
            abort_if($run->channel_id&&$run->channel_id!==$channel,409);
            $data=$page->parent_id?$this->youtube->commentReplies($page->parent_id,$page->page_token):$this->youtube->commentThreads($channel,$page->page_token);
            if(!isset($data['items'])||!is_array($data['items'])||count($data['items'])>100)throw new \RuntimeException('Invalid comment page.');
            DB::transaction(function()use($page,$channel,$data){
                $run=ChannelMessageRun::lockForUpdate()->findOrFail($page->run_id);$current=ChannelMessagePage::lockForUpdate()->findOrFail($page->id);if($current->status!=='processing')return;
                $children=[];$imported=0;
                foreach($data['items'] as $item){
                    if($page->parent_id){$this->import($item,$page->video_id,$page->parent_id);$imported++;}
                    else {$comment=$item['snippet']['topLevelComment']??[];$video=$item['snippet']['videoId']??null;$this->import($comment,$video,null);$imported++;if(($item['snippet']['totalReplyCount']??0)>0)$children[]=['parent_id'=>$comment['id'],'video_id'=>$video,'page_token'=>null];}
                }
                if(isset($data['nextPageToken'])){if(!is_string($data['nextPageToken'])||strlen($data['nextPageToken'])>4096||$data['nextPageToken']==='')throw new \RuntimeException('Invalid page token.');$children[]=['parent_id'=>$page->parent_id,'video_id'=>$page->video_id,'page_token'=>$data['nextPageToken']];}
                foreach($children as $child){$key=hash('sha256',json_encode([$child['parent_id'],$child['page_token']],JSON_THROW_ON_ERROR));if($run->pages()->where('page_key',$key)->exists())throw new \RuntimeException('Repeated comment page.');$next=$run->pages()->create(['page_key'=>$key,...$child]);SyncChannelMessagePage::dispatch($next->id)->afterCommit();}
                $pending=$run->pending_pages-1+count($children);$current->update(['status'=>'completed']);$run->update(['channel_id'=>$channel,'pending_pages'=>$pending,'pages'=>$run->pages+1,'imported'=>$run->imported+$imported,'status'=>$pending?($run->status==='failed'?'failed':'running'):'completed']);
            });
        }catch(\Throwable $error){$this->fail($id);throw new \RuntimeException(__('workspaces.comment_sync_failed'));}
    }
    private function import(array $comment,?string $video,?string $parent):void
    {
        $id=$comment['id']??null;$snippet=$comment['snippet']??[];
        if(!is_string($id)||!preg_match('/^[A-Za-z0-9_.-]{5,200}$/D',$id)||($video!==null&&!preg_match('/^[A-Za-z0-9_-]{11}$/D',$video))||!is_string($snippet['textOriginal']??null))throw new \RuntimeException('Invalid comment.');
        $sourceId='comment:'.($video??'channel').':'.$id;
        $record=SourceRecord::withTrashed()->where('source','youtube')->where('source_id',$sourceId)->lockForUpdate()->first();
        if($record?->trashed())return;
        $body=mb_substr($snippet['textOriginal'],0,100000);$metadata=$record?->metadata??[];
        $related=$video?(SourceRecord::where('source','youtube')->where('source_id',$video)->first()??Publication::with('record')->where('provider','youtube')->where('external_id',$video)->first()?->record):null;
        $remote=['body'=>$body,'updated_at'=>$snippet['updatedAt']??null];$previous=$metadata['youtube_comment']['body']??null;
        // Preserve manual text, read state, moderation and reply state. Store remote changes separately.
        if($record&&$previous!==null&&$body!==$previous)unset($metadata['inbox_read_at']);
        $metadata=[...$metadata,'comment_id'=>$id,'parent_comment_id'=>$parent,'parent_source_id'=>$video,'parent_record_id'=>$related?->id,'author'=>mb_substr((string)($snippet['authorDisplayName']??''),0,255),'posted_at'=>$snippet['publishedAt']??null,'youtube_comment'=>$remote];
        if(!$record){SourceRecord::create(['source'=>'youtube','source_id'=>$sourceId,'kind'=>'comment','title'=>Str::limit($body,120,''),'body'=>$body,'status'=>'review','metadata'=>[...$metadata,'public_published'=>false]]);return;}
        $data=['metadata'=>$metadata];if($previous!==null&&$record->body===$previous){$data['body']=$body;$data['title']=Str::limit($body,120,'');}$record->updateQuietly($data);
    }
    public function fail(int $id):void
    {
        DB::transaction(function()use($id){$candidate=ChannelMessagePage::find($id);if(!$candidate)return;$run=ChannelMessageRun::lockForUpdate()->find($candidate->run_id);$page=ChannelMessagePage::lockForUpdate()->find($id);if(!$page||$page->status==='completed')return;$page->update(['status'=>'failed']);$run?->update(['status'=>'failed','error'=>__('workspaces.comment_sync_failed')]);});
    }
}
