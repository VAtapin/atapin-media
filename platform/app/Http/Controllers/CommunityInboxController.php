<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class CommunityInboxController extends Controller
{
    public function index(Request $request)
    {
        $d=$request->validate(['q'=>'nullable|string|max:120','source'=>'nullable|string|max:32','state'=>'nullable|in:unread,read','page'=>'nullable|integer|min:1']);
        return SourceRecord::where('source','!=','catalog-reset')->whereIn('kind',['comment','live_chat','poll','post'])->where(fn($q)=>$q->whereIn('kind',['comment','live_chat','poll'])->orWhere('metadata->website_community',true))->when($d['q']??'',fn($q,$s)=>$q->where('body','like','%'.$s.'%'))->when($d['source']??'',fn($q,$s)=>$q->where('source',$s))->when(($d['state']??'')==='unread',fn($q)=>$q->whereNull('metadata->inbox_read_at'))->when(($d['state']??'')==='read',fn($q)=>$q->whereNotNull('metadata->inbox_read_at'))->latest()->paginate(30)->through(function($record){
            $parent=$this->parent($record);$targets=[];
            if($parent&&in_array($parent->kind,['video','short','post'])&&app(\App\Services\PublicContent::class)->visible($parent))$targets[]='website';
            if($record->source==='youtube'&&$record->kind==='comment'&&app(\App\Services\Publishing\ConnectionStore::class)->connected('youtube'))$targets[]='youtube';
            $record->setAttribute('reply_targets',$targets);$record->setAttribute('related_title',$parent?->title);
            $url=app(\App\Services\Importing\ImportedContentPresentation::class)->externalUrl($record->metadata??[],$record->source,$record->source_id,$record->kind);
            $video=$record->metadata['parent_source_id']??null;$comment=$record->metadata['raw']['id']??$record->metadata['comment_id']??null;
            if(!$url&&str_starts_with($record->source,'youtube')&&is_string($video)&&preg_match('/^[A-Za-z0-9_-]{11}$/',$video)&&is_string($comment)&&preg_match('/^[A-Za-z0-9_.-]{5,200}$/',$comment))$url='https://www.youtube.com/watch?v='.rawurlencode($video).'&lc='.rawurlencode($comment);
            $record->setAttribute('external_url',$url);return $record;
        });
    }
    private function eligible(SourceRecord $record): void{abort_unless(in_array($record->kind,['comment','live_chat','poll'],true)||($record->kind==='post'&&!empty($record->metadata['website_community'])),404);}
    public function read(SourceRecord $record){$this->eligible($record);\Illuminate\Support\Facades\DB::transaction(function()use($record){$record=SourceRecord::lockForUpdate()->findOrFail($record->id);$record->updateQuietly(['metadata'=>[...($record->metadata??[]),'inbox_read_at'=>now()->toIso8601String()]]);});return response()->json(['status'=>'saved']);}
    public function reply(Request $request,SourceRecord $record)
    {
        $this->eligible($record);
        $d=$request->validate(['body'=>'required|string|min:1|max:5000','target'=>'required|in:website,youtube']);
        if($d['target']==='youtube'){
            abort_unless($record->source==='youtube'&&$record->kind==='comment'&&app(\App\Services\Publishing\ConnectionStore::class)->connected('youtube'),422);
            \Illuminate\Support\Facades\DB::transaction(function()use($record,$request,$d){$record=SourceRecord::lockForUpdate()->findOrFail($record->id);abort_if(in_array($record->metadata['inbox_reply']['status']??'', ['queued','sending'],true),409);$record->updateQuietly(['metadata'=>[...($record->metadata??[]),'inbox_reply'=>['status'=>'queued']]]);\App\Jobs\ReplyYouTubeComment::dispatch($record->id,$request->user()->id,$d['body'])->afterCommit();app(Audit::class)->record('community.reply_queued',(string)$record->id);});return response()->json(['status'=>'queued'],202);
        }
        $parent=$this->parent($record);
        abort_unless($parent&&in_array($parent->kind,['video','short','post'])&&app(\App\Services\PublicContent::class)->visible($parent),422);
        $reply=SourceRecord::create(['source'=>'website','source_id'=>'website-community:'.Str::uuid(),'kind'=>'comment','title'=>Str::limit($d['body'],120,''),'body'=>$d['body'],'status'=>'ready','metadata'=>['parent_record_id'=>$parent->id,'parent_source_id'=>$parent->source_id,'reply_to_record_id'=>$record->id,'website_comment'=>true,'author'=>$request->user()->name,'author_user_id'=>$request->user()->id,'public_published'=>true,'moderation'=>['state'=>'approved']]]);
        app(Audit::class)->record('community.replied',(string)$record->id,['reply_id'=>$reply->id]);return response()->json(['status'=>'saved','id'=>$reply->id],201);
    }
    private function parent(SourceRecord $record): ?SourceRecord {return SourceRecord::find($record->metadata['parent_record_id']??0)??(!empty($record->metadata['parent_source_id'])?SourceRecord::where('source',$record->source)->where('source_id',$record->metadata['parent_source_id'])->first():$record);}
}
