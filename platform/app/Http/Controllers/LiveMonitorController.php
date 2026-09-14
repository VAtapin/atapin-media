<?php
namespace App\Http\Controllers;
use App\Models\{SourceRecord,Publication};
use App\Services\PublicContent;
use Illuminate\Support\Facades\DB;
class LiveMonitorController extends Controller
{
    public function __invoke(SourceRecord $record,PublicContent $content)
    {
        abort_unless(($record->metadata['public_section']??null)==='live',404);$metadata=$record->metadata??[];
        return response()->json(['status'=>$metadata['live_status']??'draft','started_at'=>($metadata['live_status']??null)==='live'?($metadata['live_signal_at']??null):null,
            'published'=>$content->visible($record),'online'=>DB::table('public_live_presence')->where('record_id',$record->id)->where('seen_at','>',now()->subSeconds(120))->count(),
            'recording'=>($metadata['live_recording_pending']??false)?'pending':($content->assets($record)->contains(fn($media)=>$media->kind==='video'&&$media->source==='live')?'recorded':'not_reported'),
            'chat'=>$content->children($record,'live_chat')->latest('id')->limit(30)->get(['id','body','metadata','created_at'])->reverse()->values()->map(fn($message)=>['id'=>$message->id,'body'=>$message->body,'author'=>$message->metadata['author']??'—','created_at'=>$message->created_at->toIso8601String()]),
            'outputs'=>Publication::where('source_record_id',$record->id)->latest('updated_at')->limit(20)->get(['provider','status','remote_status'])])->header('Cache-Control','private, no-store');
    }
}
