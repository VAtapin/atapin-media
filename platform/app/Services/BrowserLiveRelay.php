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
        $process=null;$stderr='';$reason='encoder_exited';$exceptionType=null;$attempts=0;
        try{
            do{
                $attempts++;$attemptStderr='';$process=$this->process($session);
                // Keep Symfony's output pipes: disabling output opens /dev/null, which Plesk open_basedir can forbid.
                $process->start(function($type,$buffer)use($process,&$attemptStderr){
                    if($type===Process::ERR)$attemptStderr=substr($attemptStderr.$buffer,-2048);
                    $process->clearOutput();$process->clearErrorOutput();
                });
                while(!$stopping&&$process->isRunning()){
                    if($invalid=$this->invalidSessionReason($session)){ $reason=$invalid;break; }
                    sleep(1);
                }
                if($process->isRunning())$process->stop(2);
                $stderr=$attemptStderr;
                if($stopping){$reason='source_unavailable';break;}
                if($reason!=='encoder_exited'||$attempts>=$this->maxAttempts()||!$this->retryable($this->errorCode($stderr)))break;
                if($invalid=$this->invalidSessionReason($session)){ $reason=$invalid;break; }
                $this->pauseBeforeRetry($attempts);
            }while(true);
        }catch(\Throwable $error){$reason='encoder_start_failed';$exceptionType=class_basename($error);
        }finally{
            if($process?->isRunning())$process->stop(2);
            $session->refresh();
            if(in_array($session->status,['starting','connected'],true)){
                app(BrowserBroadcast::class)->fail($session);
                try{Log::channel('live_browser_transport')->warning('live.browser_relay_failed',[
                    'session_id'=>$session->id,'event_id'=>$session->source_record_id,'reason'=>$reason,
                    'attempts'=>$attempts,'exit_code'=>$process?->getExitCode(),'error_code'=>$this->errorCode($stderr),'exception_type'=>$exceptionType,
                ]);}catch(\Throwable){/* Logging failure must not prevent upstream cleanup. */}
            }
            try{app(BrowserBroadcast::class)->closeUpstream($session->fresh());}catch(\Throwable){/* No secret process diagnostics in public logs. */}
        }
    }
    private function invalidSessionReason(LiveBrowserSession $session): ?string
    {
        $session->refresh();$owner=User::find($session->user_id);$record=SourceRecord::find($session->source_record_id);
        if(!in_array($session->status,['starting','connected'],true))return 'session_stopped';
        if($session->expires_at->isPast())return 'session_expired';
        if(!$owner||!$owner->can('content.publish')||!$record||!app(BrowserBroadcast::class)->eligible($record))return 'event_unavailable';
        return null;
    }
    private function retryable(string $errorCode): bool
    {
        return !in_array($errorCode,['auth_rejected','permission_denied','encoder_unavailable'],true);
    }
    protected function maxAttempts(): int { return 3; }
    protected function pauseBeforeRetry(int $attempt): void { sleep(min($attempt,2)); }
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
            str_contains($text,'could not find codec parameters'),str_contains($text,'dimensions not set'),str_contains($text,'non-existing pps')=>'codec_parameters_unavailable',
            str_contains($text,'404 not found'),str_contains($text,'method describe failed')=>'rtsp_not_ready',
            str_contains($text,'error opening output'),str_contains($text,'could not write header')=>'output_rejected',
            str_contains($text,'broken pipe'),str_contains($text,'connection reset')=>'connection_interrupted',
            str_contains($text,'invalid data')=>'invalid_media',
            default=>'unknown',
        };
    }
    protected function process(LiveBrowserSession $session): Process
    {
        // The browser is constrained to H264; keep that video bitstream and normalize available Opus audio to AAC.
        $input='rtsp://control:'.rawurlencode(app(LiveServer::class)->credentials()).'@127.0.0.1:8554/browser-'.$session->id;
        // Publish the normalized stream through MediaMTX's proven RTMP ingest path (the same path family as OBS).
        // Query authentication is MediaMTX's RTMP credential transport; RTSP remains only on the browser-source side.
        $output='rtmp://127.0.0.1:1935/live-'.$session->source_record_id.'?user=browser&pass='.rawurlencode(app(BrowserBroadcast::class)->token($session));
        $process=new Process([(string)config('platform.media_ffmpeg_binary','ffmpeg'),'-nostdin','-hide_banner','-loglevel','error',
            '-rtsp_transport','tcp','-rw_timeout','15000000','-i',$input,'-map','0:v:0','-map','0:a:0?',
            '-c:v','copy',
            '-c:a','aac','-b:a','128k','-ar','48000','-f','flv','-flvflags','no_duration_filesize',$output]);
        $process->setTimeout(null);return $process;
    }
}
