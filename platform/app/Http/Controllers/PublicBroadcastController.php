<?php
namespace App\Http\Controllers;
use App\Models\{Media,SourceRecord};
use App\Services\{PublicBroadcast,PublicContent,Settings};
use Illuminate\Http\Request;
class PublicBroadcastController extends Controller
{
    public function authenticate(Request $request,PublicBroadcast $broadcast)
    {
        $data=$request->validate(['path'=>'nullable|string|max:80','action'=>'required|in:publish,read,api,metrics','protocol'=>'nullable|in:rtmp,hls,webrtc,rtsp','ip'=>'nullable|ip','user'=>'nullable|string|max:100','password'=>'nullable|string|max:200']);
        return response('', $broadcast->authorize($data)?204:401);
    }
    public function apiIndex(Request $request,PublicContent $content)
    {
        $content->expireScheduledLives();
        $today=now(config('app.timezone'))->toDateString();
        $date=(string)$request->query('date',$today);
        try { $date=\Illuminate\Support\Carbon::createFromFormat('!Y-m-d',$date,config('app.timezone'))->format('Y-m-d'); }
        catch(\Throwable) { $date=$today; }
        $filter=match($request->query('filter','day')){'scheduled'=>'scheduled','browser_today'=>'browser_today',default=>'day'};
        if($filter==='browser_today')$date=$today;
        $perPage=20;
        $page=max(1,(int)$request->query('page',1));
        $all=SourceRecord::where('metadata->public_section','live')->get();
        $live=$all->filter(fn(SourceRecord $record)=>(($record->metadata['live_status']??null)==='live'))->sortBy(fn(SourceRecord $record)=>[
            ($record->metadata['starts_at']??null)?strtotime((string)$record->metadata['starts_at']):PHP_INT_MAX,(int)$record->id,
        ])->values();
        $events=$all->filter(function(SourceRecord $record)use($filter,$date,$content){
            $status=$record->metadata['live_status']??'draft';
            if($filter==='scheduled')return $status==='scheduled'&&$content->hasFutureStart($record);
            if($filter==='browser_today'&&($status!=='scheduled'||$record->status!=='ready'||$record->trashed()||!$content->hasFutureStart($record)))return false;
            $starts=$record->metadata['starts_at']??null;
            if(!is_string($starts)||$starts==='')return false;
            try{return \Illuminate\Support\Carbon::parse($starts,config('app.timezone'))->timezone(config('app.timezone'))->toDateString()===$date;}
            catch(\Throwable){return false;}
        })->sortBy(function(SourceRecord $record){
            $status=$record->metadata['live_status']??'draft';$starts=$record->metadata['starts_at']??null;$time=$starts?strtotime((string)$starts):PHP_INT_MAX;
            $rank=match($status){'live'=>0,'scheduled'=>1,'draft'=>2,'ended'=>3,default=>2};
            return [$rank,$rank===3?-((int)$record->id):$time,(int)$record->id];
        })->values();
        $total=$events->count();$lastPage=max(1,(int)ceil($total/$perPage));$page=min($page,$lastPage);
        $items=$events->forPage($page,$perPage)->values();
        return response()->json([
            'live'=>$live->map(fn($record)=>$this->eventData($record))->values(),
            'data'=>$items->map(fn($record)=>$this->eventData($record))->values(),
            'pagination'=>['current_page'=>$page,'per_page'=>$perPage,'total'=>$total,'last_page'=>$lastPage,'from'=>$total?(($page-1)*$perPage)+1:null,'to'=>$total?min($page*$perPage,$total):null],
            'filter'=>['mode'=>$filter,'date'=>$date],
        ]);
    }
    public function apiShow(SourceRecord $record,Settings $settings,PublicBroadcast $broadcast,PublicContent $content)
    {
        $content->expireScheduledLives();
        $this->assertLiveEvent($record);
        return response()->json(['data'=>$this->eventData($record,$broadcast->ingest($record,$settings))])->header('Cache-Control','private, no-store');
    }
    public function apiStore(Request $request,Settings $settings,?SourceRecord $record=null)
    {
        $created=!$record;
        $record=$this->persist($request,$settings,$record);
        return response()->json(['data'=>$this->eventData($record,app(PublicBroadcast::class)->ingest($record,$settings))],$created?201:200);
    }
    private function persist(Request $request,Settings $settings,?SourceRecord $record): SourceRecord
    {
        $data=$request->validate(['title'=>'required|string|max:255','body'=>'nullable|string|max:10000','starts_at'=>'nullable|date','published'=>'nullable|boolean','enabled'=>'nullable|boolean','browser_enabled'=>'nullable|boolean','rotate_key'=>'nullable|boolean','cover_media_id'=>'nullable|uuid|exists:media,id']);
        if($record && !$record->exists)$record=null;
        $this->assertLiveEvent($record);
        $previous=$record?->metadata??[];
        // Existing events used one Live flag for OBS. Preserve it as the OBS choice when the new per-mode flags are absent.
        $obsEnabled=$request->exists('enabled')?$request->boolean('enabled'):(bool)($previous['live_obs_enabled']??($previous['live_stream_enabled']??false));
        $browserEnabled=$request->exists('browser_enabled')?$request->boolean('browser_enabled'):(bool)($previous['live_browser_enabled']??false);
        $metadata=[...$previous,'public_section'=>'live','public_published'=>$request->boolean('published'),
            'live_obs_enabled'=>$obsEnabled,'live_browser_enabled'=>$browserEnabled,
            'live_stream_enabled'=>$obsEnabled||$browserEnabled,'starts_at'=>$data['starts_at']??null];
        if(!isset($metadata['live_status'])||in_array($metadata['live_status'],['draft','scheduled'],true))$metadata['live_status']=empty($metadata['starts_at'])?'draft':'scheduled';
        if(!$record)$record=SourceRecord::create(['source'=>'website','source_id'=>'live:'.\Illuminate\Support\Str::uuid(),'kind'=>'video','title'=>$data['title'],'body'=>$data['body']??'','status'=>'ready','metadata'=>$metadata]);
        else $record->update(['title'=>$data['title'],'body'=>$data['body']??'','status'=>'ready','metadata'=>$metadata]);
        if (!empty($data['cover_media_id'])) {
            app(\App\Services\Importing\ContentAssets::class)->change($record, ['action'=>'replace','role'=>'cover','media_id'=>$data['cover_media_id']]);
            $record=$record->fresh();
        }
        app(PublicBroadcast::class)->ensureSharedKey($settings,$request->boolean('rotate_key'));
        return $record->fresh();
    }
    private function assertLiveEvent(?SourceRecord $record): void
    {
        if($record)abort_unless(($record->metadata['public_section']??null)==='live',404);
    }
    private function eventData(SourceRecord $record,?array $ingest=null): array
    {
        $metadata=$record->metadata??[];
        $coverId=$metadata['cover_media_id']??null;
        $cover=$coverId?Media::find($coverId):null;
        $coverPreview=$cover && $cover->kind==='image' && app(\App\Services\MediaOriginalLocator::class)->find($cover)
            ?$cover->previewUrl():null;
        return ['id'=>$record->id,'title'=>$record->title,'body'=>$record->body,'starts_at'=>$metadata['starts_at']??null,
            'published'=>(bool)($metadata['public_published']??false),
            'enabled'=>(bool)($metadata['live_obs_enabled']??($metadata['live_stream_enabled']??false)),
            'browser_enabled'=>(bool)($metadata['live_browser_enabled']??false),
            'status'=>$metadata['live_status']??'draft','cover_media_id'=>$cover?->id,'cover_preview_url'=>$coverPreview,'created_at'=>$record->created_at?->toIso8601String(),'updated_at'=>$record->updated_at?->toIso8601String(),'ingest'=>$ingest];
    }
}
