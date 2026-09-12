<?php
namespace App\Http\Controllers;
use App\Models\ImportRun;
use App\Models\ImportItem;
use App\Services\Importing\ImportCenter;
use Illuminate\Http\Request;
class LocalVideoAuditController extends Controller
{
    public function store(Request $request,ImportCenter $center)
    {
        $run=$center->queueRun((string)$request->user()->id,'local-video-check',[]);
        return response()->json(['import_id'=>$run->id,'report_url'=>route('imports.report',$run)]);
    }
    public function browser(Request $request,ImportRun $run,ImportItem $item)
    {
        abort_unless($run->source==='local-video-check' && $item->import_run_id===$run->id && $item->type==='video-check',404);
        $data=$request->validate(['status'=>'required|in:playable,failed','reason'=>'nullable|string|max:2000']);
        $item->update(['metadata'=>[...($item->metadata??[]),'browser_status'=>$data['status'],'browser_reason'=>$data['reason']??'',
            'browser_checked_at'=>now()->toIso8601String(),'browser_checked_by'=>$request->user()->id]]);
        return response()->json(['status'=>'saved']);
    }
}
