<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\{PublicBroadcast,Settings};
use Illuminate\Http\Request;
class PublicBroadcastController extends Controller
{
    public function authenticate(Request $request,PublicBroadcast $broadcast)
    {
        $data=$request->validate(['path'=>'required|string|max:80','action'=>'required|in:publish,read','protocol'=>'required|in:rtmp,hls','user'=>'nullable|string|max:100','password'=>'nullable|string|max:200']);
        return response('', $broadcast->authorize($data)?204:401);
    }
    public function apiIndex()
    {
        return response()->json(['data'=>SourceRecord::where('metadata->public_section','live')->latest('id')->get()->map(fn($record)=>$this->eventData($record))->values()]);
    }
    public function apiShow(SourceRecord $record,Settings $settings,PublicBroadcast $broadcast)
    {
        $this->assertLiveEvent($record);
        $key=$settings->secret('live_publish_'.$record->id);
        return response()->json(['data'=>$this->eventData($record,$broadcast->ingest($record,$key))])->header('Cache-Control','private, no-store');
    }
    public function apiStore(Request $request,Settings $settings,?SourceRecord $record=null)
    {
        $created=!$record;
        $record=$this->persist($request,$settings,$record);
        $key=$settings->secret('live_publish_'.$record->id);
        return response()->json(['data'=>$this->eventData($record,app(PublicBroadcast::class)->ingest($record,$key))],$created?201:200);
    }
    private function persist(Request $request,Settings $settings,?SourceRecord $record): SourceRecord
    {
        $data=$request->validate(['title'=>'required|string|max:255','body'=>'nullable|string|max:10000','starts_at'=>'nullable|date','published'=>'nullable|boolean','enabled'=>'nullable|boolean','rotate_key'=>'nullable|boolean']);
        if($record && !$record->exists)$record=null;
        $this->assertLiveEvent($record);
        $metadata=[...($record?->metadata??[]),'public_section'=>'live','public_published'=>$request->boolean('published'),'live_stream_enabled'=>$request->boolean('enabled'),'starts_at'=>$data['starts_at']??null];
        if(!isset($metadata['live_status'])||$metadata['live_status']!=='live')$metadata['live_status']=empty($metadata['starts_at'])?'draft':'scheduled';
        if(!$record)$record=SourceRecord::create(['source'=>'website','source_id'=>'live:'.\Illuminate\Support\Str::uuid(),'kind'=>'video','title'=>$data['title'],'body'=>$data['body']??'','status'=>'ready','metadata'=>$metadata]);
        else $record->update(['title'=>$data['title'],'body'=>$data['body']??'','status'=>'ready','metadata'=>$metadata]);
        if(!$settings->hasSecret('live_publish_'.$record->id)||$request->boolean('rotate_key'))$settings->updateSecrets(['live_publish_'.$record->id=>bin2hex(random_bytes(24))]);
        return $record->fresh();
    }
    private function assertLiveEvent(?SourceRecord $record): void
    {
        if($record)abort_unless(($record->metadata['public_section']??null)==='live',404);
    }
    private function eventData(SourceRecord $record,?array $ingest=null): array
    {
        $metadata=$record->metadata??[];
        return ['id'=>$record->id,'title'=>$record->title,'body'=>$record->body,'starts_at'=>$metadata['starts_at']??null,'published'=>(bool)($metadata['public_published']??false),'enabled'=>(bool)($metadata['live_stream_enabled']??false),'status'=>$metadata['live_status']??'draft','created_at'=>$record->created_at?->toIso8601String(),'updated_at'=>$record->updated_at?->toIso8601String(),'ingest'=>$ingest];
    }
}
