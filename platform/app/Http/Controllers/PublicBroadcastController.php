<?php
namespace App\Http\Controllers;
use App\Models\{Media,SourceRecord};
use App\Services\{PublicBroadcast,PublicContent,Settings};
use Illuminate\Http\Request;
class PublicBroadcastController extends Controller
{
    public function authenticate(Request $request,PublicBroadcast $broadcast)
    {
        $data=$request->validate(['path'=>'required|string|max:80','action'=>'required|in:publish,read','protocol'=>'required|in:rtmp,hls','user'=>'nullable|string|max:100','password'=>'nullable|string|max:200']);
        return response('', $broadcast->authorize($data)?204:401);
    }
    public function apiIndex(PublicContent $content)
    {
        $content->expireScheduledLives();
        $events=SourceRecord::where('metadata->public_section','live')->get()->sortBy(function(SourceRecord $record){
            $status=$record->metadata['live_status']??'draft';$starts=$record->metadata['starts_at']??null;$time=$starts?strtotime((string)$starts):PHP_INT_MAX;
            $rank=match($status){'live'=>0,'scheduled'=>1,'draft'=>2,'ended'=>3,default=>2};
            return [$rank,$rank===3?-((int)$record->id):$time,(int)$record->id];
        })->values();
        return response()->json(['data'=>$events->map(fn($record)=>$this->eventData($record))->values()]);
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
        $data=$request->validate(['title'=>'required|string|max:255','body'=>'nullable|string|max:10000','starts_at'=>'nullable|date','published'=>'nullable|boolean','enabled'=>'nullable|boolean','rotate_key'=>'nullable|boolean','cover_media_id'=>'nullable|uuid|exists:media,id']);
        if($record && !$record->exists)$record=null;
        $this->assertLiveEvent($record);
        $metadata=[...($record?->metadata??[]),'public_section'=>'live','public_published'=>$request->boolean('published'),'live_stream_enabled'=>$request->boolean('enabled'),'starts_at'=>$data['starts_at']??null];
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
        return ['id'=>$record->id,'title'=>$record->title,'body'=>$record->body,'starts_at'=>$metadata['starts_at']??null,'published'=>(bool)($metadata['public_published']??false),'enabled'=>(bool)($metadata['live_stream_enabled']??false),'status'=>$metadata['live_status']??'draft','cover_media_id'=>$cover?->id,'cover_preview_url'=>$coverPreview,'created_at'=>$record->created_at?->toIso8601String(),'updated_at'=>$record->updated_at?->toIso8601String(),'ingest'=>$ingest];
    }
}
