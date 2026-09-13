<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\PublicLiveState;
use Illuminate\Http\Request;
class PublicLiveController extends Controller
{
    public function heartbeat(Request $request,SourceRecord $record,PublicLiveState $state)
    {
        return response()->json($state->heartbeat($record,$request->session()->getId()))->header('Cache-Control','private, no-store');
    }
}
