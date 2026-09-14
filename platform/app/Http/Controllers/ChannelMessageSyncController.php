<?php
namespace App\Http\Controllers;
use App\Models\ChannelMessageRun;
use App\Services\Publishing\YouTubeCommentSync;
use Illuminate\Http\Request;
class ChannelMessageSyncController extends Controller
{
    public function index(){return response()->json(['data'=>ChannelMessageRun::where('provider','youtube')->latest()->limit(10)->get(['id','provider','status','pages','pending_pages','imported','error','created_at','updated_at'])])->header('Cache-Control','private, no-store');}
    public function store(Request $request,YouTubeCommentSync $sync){$request->validate(['confirm'=>'required|accepted']);$run=$sync->start($request->user());return response()->json(['id'=>$run->id,'status'=>'queued'],202);}
    public function retry(Request $request,ChannelMessageRun $run,YouTubeCommentSync $sync){$sync->retry($run,$request->user());return response()->json(['id'=>$run->id,'status'=>'queued'],202);}
}
