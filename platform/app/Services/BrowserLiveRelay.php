<?php
namespace App\Services;
use App\Models\{LiveBrowserSession,SourceRecord,User};
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
class BrowserLiveRelay
{
    public function run(string $path): void
    {
        if(!preg_match('/^browser-([a-f0-9-]{36})$/D',$path,$match))return;
        $session=app(BrowserBroadcast::class)->active()->find($match[1]);if(!$session)return;
        $stopping=false;
        if(function_exists('pcntl_async_signals')){
            pcntl_async_signals(true);
            pcntl_signal(SIGINT,static function()use(&$stopping){$stopping=true;});
            pcntl_signal(SIGTERM,static function()use(&$stopping){$stopping=true;});
        }
        $process=$this->process($session);$stderr='';$reason='encoder_exited';$exceptionType=null;
        try{
            // Keep Symfony's output pipes: disabling output opens /dev/null, which Plesk open_basedir can forbid.
            $process->start(function($type,$buffer)use($process,&$stderr){
                if($type===Process::ERR)$stderr=substr($stderr.$buffer,-2048);
                $process->clearOutput();$process->clearErrorOutput();
            });
            while(!$stopping&&$process->isRunning()){
                $session->refresh();$owner=User::find($session->user_id);$record=SourceRecord::find($session->source_record_id);
                if(!in_array($session->status,['starting','connected'],true)){ $reason='session_stopped';break; }
                if($session->expires_at->isPast()){ $reason='session_expired';break; }
                if(!$owner||!$owner->can('content.publish')||!$record||!app(BrowserBroadcast::class)->eligible($record)){ $reason='event_unavailable';break; }
                sleep(1);
            }
        }catch(\Throwable $error){$reason='encoder_start_failed';$exceptionType=class_basename($error);
        }finally{
            if($process->isRunning())$process->stop(2);
            $session->refresh();
            if(in_array($session->status,['starting','connected'],true)){
                app(BrowserBroadcast::class)->fail($session);
                try{Log::channel('live_browser_transport')->warning('live.browser_relay_failed',[
                    'session_id'=>$session->id,'event_id'=>$session->source_record_id,'reason'=>$reason,
                    'exit_code'=>$process->getExitCode(),'error_code'=>$this->errorCode($stderr),'exception_type'=>$exceptionType,
                ]);}catch(\Throwable){/* Logging failure must not prevent upstream cleanup. */}
            }
            try{app(BrowserBroadcast::class)->closeUpstream($session->fresh());}catch(\Throwable){/* No secret process diagnostics in public logs. */}
        }
    }
    private function errorCode(string $stderr): string
    {
        $text=strtolower($stderr);
        return match(true){
            str_contains($text,'401 unauthorized'),str_contains($text,'authorization failed')=>'auth_rejected',
            str_contains($text,'connection refused')=>'connection_refused',
            str_contains($text,'permission denied')=>'permission_denied',
            str_contains($text,'timed out')=>'media_timeout',
            str_contains($text,'unknown encoder'),str_contains($text,'encoder not found')=>'encoder_unavailable',
            str_contains($text,'matches no streams'),str_contains($text,'does not contain any stream')=>'media_track_missing',
            str_contains($text,'error opening output'),str_contains($text,'could not write header')=>'output_rejected',
            str_contains($text,'invalid data')=>'invalid_media',
            default=>'unknown',
        };
    }
    protected function process(LiveBrowserSession $session): Process
    {
        // The browser is constrained to H264; keep that video bitstream and normalize available Opus audio to AAC.
        $input='rtsp://control:'.rawurlencode(app(LiveServer::class)->credentials()).'@127.0.0.1:8554/browser-'.$session->id;
        $output='rtsp://browser:'.rawurlencode(app(BrowserBroadcast::class)->token($session)).'@127.0.0.1:8554/live-'.$session->source_record_id;
        $process=new Process([(string)config('platform.media_ffmpeg_binary','ffmpeg'),'-nostdin','-hide_banner','-loglevel','error',
            '-rtsp_transport','tcp','-rw_timeout','15000000','-i',$input,'-map','0:v:0','-map','0:a:0?',
            '-c:v','copy',
            '-c:a','aac','-b:a','128k','-ar','48000','-f','rtsp','-rtsp_transport','tcp',$output]);
        $process->setTimeout(null);return $process;
    }
}
