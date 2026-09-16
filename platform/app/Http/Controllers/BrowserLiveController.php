<?php
namespace App\Http\Controllers;
use App\Models\{SourceRecord,LiveBrowserSession};
use App\Services\{BrowserBroadcast,LiveServer,Settings};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class BrowserLiveController extends Controller
{
    public function status(Request $request,LiveServer $server)
    {
        return response()->json([...$server->status(),'can_configure'=>Gate::allows('settings.manage'),
            'can_disconnect'=>Gate::allows('live.manage'),
            'session'=>app(BrowserBroadcast::class)->active()->where('user_id',$request->user()->id)->first(['id','source_record_id']),
            'host'=>app(Settings::class)->get('live_browser_host',config('platform.live_rtmp_host')),
            'labels'=>__('live-browser')])->header('Cache-Control','private, no-store');
    }
    public function configure(Request $request,LiveServer $server)
    {
        Gate::authorize('settings.manage');
        $data=$request->validate(['confirm'=>'required|accepted','live_browser_enabled'=>'required|boolean',
            'live_browser_host'=>['required','string','max:253','regex:/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/D']]);
        unset($data['confirm']);$server->configure($data);
        return response()->json($server->status())->header('Cache-Control','private, no-store');
    }
    public function start(Request $request,SourceRecord $record,BrowserBroadcast $broadcast)
    {
        $data=$request->validate(['confirm'=>'required|accepted','sdp'=>'required|string|max:262144']);
        abort_unless(str_starts_with($data['sdp'],'v=0')&&str_contains($data['sdp'],'m=video')&&str_contains($data['sdp'],'m=audio'),422);
        // Laravel trims JSON strings; SDP parsers require a final line terminator.
        $sdp=rtrim(str_replace(["\r\n","\r"],"\n",$data['sdp']),"\n");
        $sdp=str_replace("\n","\r\n",$sdp)."\r\n";
        return response()->json($broadcast->begin($request->user(),$record,$sdp),201)->header('Cache-Control','private, no-store');
    }
    public function heartbeat(Request $request,LiveBrowserSession $session,BrowserBroadcast $broadcast)
    {
        return response()->json($broadcast->heartbeat($session,$request->user()))->header('Cache-Control','private, no-store');
    }
    public function stop(Request $request,LiveBrowserSession $session,BrowserBroadcast $broadcast)
    {
        $broadcast->stop($session,$request->user());return response()->noContent();
    }
    public function disconnect(Request $request,SourceRecord $record,LiveServer $server)
    {
        Gate::authorize('live.manage');$request->validate(['confirm'=>'required|accepted']);
        abort_unless(($record->metadata['public_section']??null)==='live',404);
        // Disable reconnects before kicking OBS; the user can explicitly re-enable the event.
        $record->update(['metadata'=>[...$record->metadata,'live_obs_enabled'=>false,
            'live_stream_enabled'=>(bool)($record->metadata['live_browser_enabled']??false)]]);
        $server->disconnect($record);return response()->noContent();
    }
    public function podcast(Request $request,SourceRecord $record,\App\Services\RecordedPodcast $podcasts)
    {
        Gate::authorize('content.edit');Gate::authorize('media.view');
        $data=$request->validate(['confirm'=>'required|accepted','media_id'=>'required|uuid|exists:media,id']);
        $draft=$podcasts->draft($request->user(),$record,\App\Models\Media::findOrFail($data['media_id']));
        return response()->json(['id'=>$draft->id,'detail_url'=>route('content.show',$draft)],201)->header('Cache-Control','private, no-store');
    }
}
