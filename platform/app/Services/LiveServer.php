<?php
namespace App\Services;
use Illuminate\Support\Facades\{Cache,Http,Log};
use Symfony\Component\Process\Process;
class LiveServer
{
    public function credentials(): string
    {
        $key=app(Settings::class)->secret('live_control');
        abort_unless(is_string($key)&&strlen($key)>=32,503,__('live-browser.server_unavailable'));
        return $key;
    }
    public function request(string $method,string $path)
    {
        // Never accept an arbitrary URL, command, or MediaMTX configuration endpoint.
        abort_unless(($method==='GET'&&$path==='/v3/paths/list')||($method==='POST'&&preg_match('~^/v3/(rtmp|rtmps)/conns/kick/[a-f0-9-]{36}$~D',$path)),422);
        try {
            $response=Http::withOptions(['proxy'=>'','allow_redirects'=>false])->withBasicAuth('control',$this->credentials())
                ->connectTimeout(2)->timeout(5)->send($method,'http://127.0.0.1:9997'.$path);
            abort_unless($response->successful(),503,__('live-browser.server_unavailable'));
            return $response;
        } catch (\Illuminate\Http\Client\ConnectionException) { abort(503,__('live-browser.server_unavailable')); }
    }
    public function paths(): array
    {
        $items=$this->request('GET','/v3/paths/list')->json('items');
        abort_unless(is_array($items)&&count($items)<=1000,503,__('live-browser.server_unavailable'));
        return $items;
    }
    public function status(): array
    {
        try {
            $paths=$this->paths();
            return ['available'=>true,'browser_enabled'=>(bool)app(Settings::class)->get('live_browser_enabled',false),
                'active'=>collect($paths)->filter(fn($row)=>!empty($row['ready']))->map(fn($row)=>[
                    'name'=>$row['name']??'', 'bytes_received'=>$row['bytesReceived']??null,
                    'readers'=>count($row['readers']??[]),'source'=>$row['source']['type']??null,
                ])->values()->all()];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            return ['available'=>false,'browser_enabled'=>(bool)app(Settings::class)->get('live_browser_enabled',false),'active'=>[]];
        }
    }
    public function configure(array $values): void
    {
        Cache::lock('live-server-configuration',30)->block(3,function()use($values){
            // Plesk installation belongs to the subscription user, never root or arbitrary paths.
            abort_unless(PHP_OS_FAMILY==='Linux'&&basename(dirname(base_path()))==='httpdocs',503,__('live-browser.server_setup_required'));
            $root=dirname(base_path(),2).'/private/atapin-live';
            $binary=$root.'/mediamtx';
            abort_unless(is_executable($binary),503,__('live-browser.server_setup_required'));
            if($values['live_browser_enabled']){
                $encoder=new Process([(string)config('platform.media_ffmpeg_binary','ffmpeg'),'-hide_banner','-encoders']);$encoder->setTimeout(5);
                try{$encoder->mustRun();abort_unless(preg_match('/\baac\b/',$encoder->getOutput()),503,__('live-browser.encoder_required'));}
                catch(\Symfony\Component\Process\Exception\ExceptionInterface){abort(503,__('live-browser.encoder_required'));}
            }
            abort_if(\App\Models\LiveBrowserSession::whereIn('status',['starting','connected'])->where('expires_at','>',now())->exists(),409,__('live-browser.server_busy'));
            // Never reconfigure an on-air source. API may still be disabled before first setup.
            abort_if(collect($this->status()['active'])->isNotEmpty()||\App\Models\SourceRecord::where('metadata->public_section','live')->where('metadata->live_status','live')->exists(),409,__('live-browser.server_busy'));
            $settings=app(Settings::class);
            $previous=['live_browser_enabled'=>$settings->get('live_browser_enabled',false),'live_browser_host'=>$settings->get('live_browser_host','')];
            if(!$settings->hasSecret('live_control'))$settings->updateSecrets(['live_control'=>bin2hex(random_bytes(32))]);
            $candidate=$root.'/mediamtx.candidate.yml';$current=$root.'/mediamtx.yml';
            $applied=false;$backedUp=false;$stage='settings';
            try {
                $settings->update($values);
                $stage='configuration';
                $content=app(PublicBroadcast::class)->configuration();
                $stage='write';
                if(file_put_contents($candidate,$content,LOCK_EX)===false)throw new \RuntimeException();
                chmod($candidate,0600);
                $stage='validation';
                $this->runServerCommand([$binary,'--validate-conf',$candidate],10);
                $stage='backup';
                if(is_file($current)){$backedUp=copy($current,$root.'/mediamtx.backup.yml');if(!$backedUp)throw new \RuntimeException();}
                if(is_file($root.'/mediamtx.backup.yml'))chmod($root.'/mediamtx.backup.yml',0600);
                $stage='replace';
                if(!rename($candidate,$current))throw new \RuntimeException();
                $applied=true;
                // MediaMTX reloads changed configuration; start is idempotent when already running.
                $stage='start';
                $this->runServerCommand(['/bin/bash',base_path('bin/live-server.sh'),'start'],10);
                $stage='audit';
                app(Audit::class)->record('live.server_configured',null,['browser_enabled'=>$values['live_browser_enabled']]);
                $this->logConfigurationAttempt('applied','complete',(bool)$values['live_browser_enabled']);
            }catch(\Throwable $error){
                $settings->update($previous);
                $restored=true;
                if($applied){
                    $previousContent=$backedUp?file_get_contents($root.'/mediamtx.backup.yml'):app(PublicBroadcast::class)->configuration();
                    $restored=is_string($previousContent)&&file_put_contents($candidate,$previousContent,LOCK_EX)!==false;
                    if($restored){chmod($candidate,0600);$restored=rename($candidate,$current);}
                }
                if(is_file($candidate))unlink($candidate);
                $this->logConfigurationAttempt($restored?'rolled_back':'rollback_unconfirmed',$stage,(bool)$values['live_browser_enabled'],$error);
                abort(503,__($restored?'live-browser.server_configuration_failed_stage':'live-browser.server_rollback_failed',
                    ['step'=>__('live-browser.config_step_'.$stage)]));
            }
        });
    }
    private function runServerCommand(array $command,int $timeout): void
    {
        // Keep output pipes: disabling them opens /dev/null, which Plesk PHP open_basedir can forbid.
        $process=new Process($command);$process->setTimeout($timeout);$process->mustRun();
    }
    private function logConfigurationAttempt(string $outcome,string $stage,bool $browserEnabled,?\Throwable $error=null): void
    {
        $context=['outcome'=>$outcome,'stage'=>$stage,'browser_enabled'=>$browserEnabled];
        if($error){
            $context['error_type']=$error::class;
            $context['error_source']=basename($error->getFile()).':'.$error->getLine();
            if($error instanceof \ErrorException && str_contains($error->getMessage(),'/dev/null'))
                $context['warning_code']='dev_null_unavailable';
            if($error instanceof \Symfony\Component\Process\Exception\ProcessFailedException)
                $context['process_exit_code']=$error->getProcess()->getExitCode();
        }
        try{Log::channel('live_server')->log($error?'warning':'info','live.server_configuration_'.$outcome,$context);}
        catch(\Throwable){/* A logging problem must not change the server or rollback result. */}
    }
    public function disconnect(\App\Models\SourceRecord $record): void
    {
        $paths=$this->paths();$found=false;
        foreach($paths as $row){
            $name=$row['name']??'';
            if($name!=='live-'.$record->id&&$name!=='live')continue;
            if($name==='live'&&app(PublicBroadcast::class)->record('live')?->id!==$record->id)continue;
            $source=$row['source']??[];
            if(!in_array($source['type']??'',['rtmpConn','rtmpsConn'],true)||!preg_match('/^[a-f0-9-]{36}$/D',$source['id']??''))continue;
            $protocol=$source['type']==='rtmpsConn'?'rtmps':'rtmp';
            $this->request('POST','/v3/'.$protocol.'/conns/kick/'.$source['id']);$found=true;
        }
        abort_unless($found,409,__('live-browser.no_active_source'));
        app(Audit::class)->record('live.input_disconnected',(string)$record->id);
    }
}
