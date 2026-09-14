<?php
namespace App\Services;
use App\Models\{LiveBrowserSession,SourceRecord,User};
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
        $process=$this->process($session);$process->start();
        try{
            while(!$stopping&&$process->isRunning()){
                $session->refresh();$owner=User::find($session->user_id);$record=SourceRecord::find($session->source_record_id);
                if(!in_array($session->status,['starting','connected'],true)||$session->expires_at->isPast()||!$owner||!$owner->can('content.publish')||!$record||!app(BrowserBroadcast::class)->eligible($record))break;
                sleep(1);
            }
        }finally{
            $process->stop(2);
            if(in_array($session->status,['starting','connected'],true))$session->update(['status'=>'failed','expires_at'=>now()]);
            try{app(BrowserBroadcast::class)->closeUpstream($session->fresh());}catch(\Throwable){/* No secret process diagnostics in public logs. */}
        }
    }
    protected function process(LiveBrowserSession $session): Process
    {
        // Browser H264/Opus is normalized to H264/AAC for the existing HLS/MP4/RTMP pipeline.
        $input='rtsp://control:'.rawurlencode(app(LiveServer::class)->credentials()).'@127.0.0.1:8554/browser-'.$session->id;
        $output='rtmp://127.0.0.1:1935/live-'.$session->source_record_id.'?user=browser&pass='.app(BrowserBroadcast::class)->token($session);
        $process=new Process([(string)config('platform.media_ffmpeg_binary','ffmpeg'),'-nostdin','-hide_banner','-loglevel','error',
            '-rtsp_transport','tcp','-rw_timeout','15000000','-i',$input,'-map','0:v:0','-map','0:a:0',
            '-c:v','libx264','-preset','veryfast','-tune','zerolatency','-pix_fmt','yuv420p','-profile:v','baseline','-g','60','-b:v','2500k',
            '-c:a','aac','-b:a','128k','-ar','48000','-f','flv',$output]);
        $process->setTimeout(null);$process->disableOutput();return $process;
    }
}
