<?php
namespace App\Http\Controllers;
use App\Services\DesktopAnalytics;
use Illuminate\Http\Request;
class AnalyticsController extends Controller
{
    public function index(Request $request,DesktopAnalytics $analytics){$d=$request->validate(['start'=>'required|date_format:Y-m-d','end'=>'required|date_format:Y-m-d|after_or_equal:start']);abort_if(\Illuminate\Support\Carbon::parse($d['start'])->diffInDays($d['end'])>366,422);return response()->json($analytics->report($d['start'],$d['end']));}
    public function export(Request $request,DesktopAnalytics $analytics)
    {
        $data=$this->index($request,$analytics)->getData(true);return response()->streamDownload(function()use($data){$out=fopen('php://output','w');fputcsv($out,['date','events'],',','"','');foreach($data['daily'] as $row)fputcsv($out,[$row['occurred_on'],$row['count']],',','"','');fclose($out);},'analytics.csv',['Content-Type'=>'text/csv']);
    }
    public function audio(Request $request,\App\Models\SourceRecord $record,DesktopAnalytics $analytics){abort_unless(app(\App\Services\PublicContent::class)->visible($record)&&($record->metadata['public_section']??'')==='podcast',404);$analytics->track($request,'audio_play',(string)$record->id);return response()->json(['status'=>'counted']);}
}
