<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\PublicLiveState;
use Illuminate\Http\Request;
class PublicLiveController extends Controller
{
    public function current(\App\Services\PublicContent $content)
    {
        return response()->json(['live'=>$content->forSection('live')->where('metadata->live_status','live')
            ->where('metadata->live_stream_enabled',true)->exists()])->header('Cache-Control','no-store');
    }

    public function heartbeat(Request $request,SourceRecord $record,PublicLiveState $state)
    {
        return response()->json($state->heartbeat($record,$request->session()->getId(),$request->user()))->header('Cache-Control','private, no-store');
    }
}
