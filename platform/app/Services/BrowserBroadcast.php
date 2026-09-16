<?php
namespace App\Services;
use App\Models\{LiveBrowserSession,SourceRecord,User};
use Illuminate\Support\Facades\{Cache,DB,Http};
use Illuminate\Support\Str;
class BrowserBroadcast
{
    public function token(LiveBrowserSession $session): string
    {
        return hash_hmac('sha256',$session->id,app(LiveServer::class)->credentials());
    }
    public function active(): \Illuminate\Database\Eloquent\Builder
    {
        return LiveBrowserSession::whereIn('status',['starting','connected'])->where('expires_at','>',now());
    }
    public function eligible(SourceRecord $record): bool
    {
        return $this->selectable($record)
            &&!empty($record->metadata['live_browser_enabled'])&&!empty($record->metadata['public_published']);
    }
    private function selectable(SourceRecord $record): bool
    {
        return !$record->trashed()&&$record->status==='ready'&&($record->metadata['public_section']??null)==='live';
    }
    public function begin(User $user,SourceRecord $record,string $sdp): array
    {
        abort_unless($this->selectable($record),422,__('live-browser.browser_event_required'));
        abort_unless(app(Settings::class)->get('live_browser_enabled',false),503,__('live-browser.browser_disabled'));
        $previous=[];
        $session=Cache::lock('live-input-owner',15)->block(3,function()use($user,$record,&$previous){
            abort_if($this->active()->exists(),409,__('live-browser.server_busy'));
            abort_if(SourceRecord::where('metadata->public_section','live')->where('metadata->live_ingest_reserved_until','>',now()->toIso8601String())->exists(),409,__('live-browser.server_busy'));
            $paths=app(LiveServer::class)->paths();
            abort_if(collect($paths)->contains(fn($row)=>!empty($row['ready'])&&(str_starts_with($row['name']??'','live')||str_starts_with($row['name']??'','browser-'))),409,__('live-browser.server_busy'));
            return DB::transaction(function()use($user,$record,&$previous){
                $selected=SourceRecord::lockForUpdate()->findOrFail($record->id);
                abort_unless($this->selectable($selected),422,__('live-browser.browser_event_required'));
                $metadata=$selected->metadata??[];
                $flags=['public_published','live_obs_enabled','live_browser_enabled','live_stream_enabled','starts_at'];
                $previous=array_intersect_key($metadata,array_flip($flags));
                $session=LiveBrowserSession::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'source_record_id'=>$selected->id,'expires_at'=>now()->addMinutes(2)]);
                $selected->update(['metadata'=>[...$metadata,'public_published'=>true,
                    'live_obs_enabled'=>(bool)($metadata['live_obs_enabled']??($metadata['live_stream_enabled']??false)),
                    'live_browser_enabled'=>true,'live_stream_enabled'=>true,
                    'starts_at'=>$metadata['starts_at']??now()->toIso8601String(),
                    'live_browser_start_session_id'=>$session->id]]);
                return $session;
            });
        });
        try{
            $response=$this->transport($session)->withBody($sdp,'application/sdp')->post('http://127.0.0.1:8889/browser-'.$session->id.'/whip');
            abort_unless($response->status()===201&&strlen($response->body())<=262144,503,__('live-browser.browser_connection_failed'));
            $location=$response->header('Location');
            // Save only a validated local session resource; never forward arbitrary upstream URLs.
            $prefix='/browser-'.$session->id.'/whip/';
            if(str_starts_with($location,'http://127.0.0.1:8889/'))$location=substr($location,strlen('http://127.0.0.1:8889'));
            abort_unless(str_starts_with($location,$prefix)&&preg_match('~^'.preg_quote($prefix,'~').'[a-f0-9-]{36}$~D',$location),503,__('live-browser.browser_connection_failed'));
            $session->update(['status'=>'connected','upstream_location'=>$location]);
            app(Audit::class)->record('live.browser_started',(string)$record->id,['session_id'=>$session->id]);
            $this->finishStart($session,$previous,false);
            return ['id'=>$session->id,'sdp'=>$response->body(),'expires_at'=>$session->expires_at->toIso8601String(),
                'starts_at'=>$record->fresh()->metadata['starts_at']??null];
        }catch(\Throwable){$session->update(['status'=>'failed','expires_at'=>now()]);$this->finishStart($session,$previous,true);abort(503,__('live-browser.browser_connection_failed'));}
    }
    private function finishStart(LiveBrowserSession $session,array $previous,bool $rollback): void
    {
        DB::transaction(function()use($session,$previous,$rollback){
            $record=SourceRecord::lockForUpdate()->find($session->source_record_id);if(!$record)return;
            $metadata=$record->metadata??[];if(($metadata['live_browser_start_session_id']??null)!==$session->id)return;
            unset($metadata['live_browser_start_session_id']);
            if($rollback)foreach(['public_published','live_obs_enabled','live_browser_enabled','live_stream_enabled','starts_at'] as $key){
                if(array_key_exists($key,$previous))$metadata[$key]=$previous[$key];else unset($metadata[$key]);
            }
            $record->update(['metadata'=>$metadata]);
        });
    }
    private function transport(LiveBrowserSession $session)
    {
        return Http::withOptions(['proxy'=>'','allow_redirects'=>false])->withBasicAuth('browser',$this->token($session))->connectTimeout(2)->timeout(15);
    }
    public function heartbeat(LiveBrowserSession $session,User $user): array
    {
        abort_unless($session->user_id===$user->id,403);
        return DB::transaction(function()use($session){
            $session=LiveBrowserSession::lockForUpdate()->findOrFail($session->id);
            abort_unless($session->status==='connected'&&$session->expires_at->isFuture()&&$this->eligible(SourceRecord::findOrFail($session->source_record_id)),409,__('live-browser.browser_session_ended'));
            $session->update(['expires_at'=>now()->addMinutes(2)]);
            $record=SourceRecord::findOrFail($session->source_record_id);
            return ['status'=>$record->metadata['live_status']??'starting','expires_at'=>$session->expires_at->toIso8601String()];
        });
    }
    public function stop(LiveBrowserSession $session,User $user): void
    {
        abort_unless($session->user_id===$user->id,403);
        if($session->status==='ended'&&!$session->upstream_location)return;
        $session->update(['status'=>'ended','expires_at'=>now()]);
        $this->closeUpstream($session);
        app(Audit::class)->record('live.browser_stopped',(string)$session->source_record_id,['session_id'=>$session->id]);
    }
    public function closeUpstream(LiveBrowserSession $session): void
    {
        if(!$session->upstream_location)return;
        try{$response=$this->transport($session)->delete('http://127.0.0.1:8889'.$session->upstream_location);abort_unless($response->successful()||$response->status()===404,503);$session->update(['upstream_location'=>null]);}
        catch(\Throwable){abort(503,__('live-browser.browser_stop_pending'));}
    }
    public function authorize(array $data): ?bool
    {
        $path=$data['path']??'';$user=$data['user']??'';$action=$data['action']??'';$protocol=$data['protocol']??'';
        if($user==='control'){
            if(!in_array($data['ip']??'',['127.0.0.1','::1'],true))return false;
            $key=app(Settings::class)->secret('live_control');
            if(!is_string($key)||!hash_equals($key,(string)($data['password']??'')))return false;
            return in_array($action,['api','metrics'],true)||($action==='read'&&$protocol==='rtsp'&&preg_match('/^browser-[a-f0-9-]{36}$/D',$path)&&$this->active()->whereKey(substr($path,8))->exists());
        }
        if($user!=='browser')return str_starts_with($path,'browser-')||in_array($action,['api','metrics'],true)?false:null;
        if($action!=='publish')return false;
        $session=($protocol==='webrtc'&&str_starts_with($path,'browser-'))?$this->active()->find(substr($path,8)):
            (($protocol==='rtmp'&&preg_match('/^live-([1-9][0-9]*)$/D',$path,$match))?$this->active()->where('source_record_id',$match[1])->first():null);
        if(!$session||!hash_equals($this->token($session),(string)($data['password']??'')))return false;
        $owner=User::find($session->user_id);$record=SourceRecord::find($session->source_record_id);
        return $owner&&$owner->can('content.publish')&&$record&&$this->eligible($record);
    }
}
