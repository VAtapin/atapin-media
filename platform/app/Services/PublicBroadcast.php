<?php
namespace App\Services;
use App\Models\{SourceRecord,Media};
use Illuminate\Support\Facades\{DB,Storage};
class PublicBroadcast
{
    public const SHARED_PATH = 'live';

    public function secureIngestReady(): bool
    {
        return is_readable((string) config('platform.live_rtmp_cert'))
            && is_readable((string) config('platform.live_rtmp_key'));
    }

    public function ensureSharedKey(Settings $settings, bool $rotate = false): string
    {
        if (!$settings->hasSecret('live_publish_shared') || $rotate) {
            $settings->updateSecrets(['live_publish_shared' => bin2hex(random_bytes(24))]);
        }
        return (string) $settings->secret('live_publish_shared');
    }

    public function ingest(SourceRecord $record, Settings $settings, bool $rotate = false): array
    {
        $host = (string) config('platform.live_rtmp_host');
        $port = (int) config('platform.live_rtmp_port');
        $key = $this->ensureSharedKey($settings, $rotate);
        $url = 'rtmps://'.$host.':'.$port.'/'.self::SHARED_PATH.'?user=publisher&pass='.rawurlencode($key);

        return ['configured' => $this->secureIngestReady(), 'url' => $url, 'path' => self::SHARED_PATH, 'host' => $host, 'port' => $port];
    }

    public function record(string $path): ?SourceRecord
    {
        if ($path === self::SHARED_PATH) return $this->activeRecord() ?? $this->candidateRecord();
        if(!preg_match('/^live-([1-9][0-9]*)$/D',$path,$match))return null;
        return SourceRecord::whereKey($match[1])->where('metadata->public_section','live')->where('metadata->live_stream_enabled',true)->first();
    }
    public function authorize(array $data): bool
    {
        $browser=app(BrowserBroadcast::class)->authorize($data);
        if($browser!==null)return $browser;
        $path=$data['path']??'';
        if(($data['action']??'')==='read'){
            $record=$this->record($path);return $record&&($data['protocol']??'')==='hls'&&app(PublicContent::class)->visible($record);
        }
        if(($data['action']??'')!=='publish'||($data['protocol']??'')!=='rtmp'||($data['user']??'')!=='publisher')return false;
        return \Illuminate\Support\Facades\Cache::lock('live-input-owner',15)->block(3,fn()=>$this->authorizeObs($data));
    }
    private function authorizeObs(array $data): bool
    {
        $path=$data['path']??'';
        if(app(BrowserBroadcast::class)->active()->exists())return false;
        $record=$path===self::SHARED_PATH?null:$this->record($path);
        $key=$path===self::SHARED_PATH?app(Settings::class)->secret('live_publish_shared'):app(Settings::class)->secret('live_publish_'.($record?->id ?? ''));
        if(!is_string($key)||!is_string($data['password']??null)||!hash_equals($key,$data['password']))return false;
        $selected=$path===self::SHARED_PATH?$this->activateSharedRecord():$record;
        if(!$selected)return false;
        $selected->update(['metadata'=>[...$selected->metadata,'live_ingest_reserved_until'=>now()->addSeconds(30)->toIso8601String()]]);
        return true;
    }
    public function signal(string $path,bool $ready): void
    {
        $record=$path===self::SHARED_PATH?$this->activeRecord():$this->record($path);if(!$record)return;
        DB::transaction(function()use($record,$ready){
            $record=SourceRecord::lockForUpdate()->findOrFail($record->id);
            if ($ready && ($record->metadata['live_status'] ?? null) === 'ended') return;
            $metadata=[...$record->metadata,'live_status'=>$ready?'live':'ended','live_signal_at'=>now()->toIso8601String()];
            unset($metadata['live_ingest_reserved_until']);
            if ($ready) unset($metadata['live_recording_pending']);
            else {
                unset($metadata['live_ingest_active']);
                $metadata['live_recording_pending']=true;
            }
            $record->update(['metadata'=>$metadata]);
        });
    }
    public function recording(string $path,string $file): void
    {
        $record=$path===self::SHARED_PATH?$this->recordingRecord():$this->record($path);if(!$record)return;
        $root=Storage::disk('live-recordings')->path('');
        $rootPath=rtrim(realpath($root) ?: $root, '/\\');
        $candidate=str_replace('\\','/',$file);
        $prefix=str_replace('\\','/',$rootPath).'/';
        if(str_starts_with($candidate,$prefix)) {
            $relative=substr($candidate,strlen($prefix));
            \App\Services\Importing\ImportPath::entry($relative);
            if(str_starts_with($relative,$path.'/') && Media::where('source','live')->where('source_id',hash('sha256',$relative))->exists())return;
        }
        $resolved=\App\Services\Importing\ImportPath::resolve($root,$file);
        if(!is_file($resolved)||is_link($file)||strtolower(pathinfo($resolved,PATHINFO_EXTENSION))!=='mp4')throw new \RuntimeException('Invalid completed recording.');
        $relative=str_replace('\\','/',substr($resolved,strlen(rtrim(realpath($root),'/\\'))+1));
        if(!str_starts_with($relative,$path.'/'))throw new \RuntimeException('Recording belongs to another stream.');
        $stored=app(CanonicalMediaStorage::class)->storePath($resolved,'video/mp4');
        $media = DB::transaction(function()use($record,$stored,$relative){
            $record=SourceRecord::lockForUpdate()->findOrFail($record->id);
            $media=app(\App\Services\Importing\ImportedMediaRegistry::class)->register(['source'=>'live','source_id'=>hash('sha256',$relative),'disk'=>$stored['disk'],'path'=>$stored['path'],'sha256'=>$stored['sha256'],'kind'=>'video','mime'=>'video/mp4','bytes'=>$stored['bytes'],'title'=>$record->title,'original_name'=>$stored['filename'],'status'=>'unsorted','metadata'=>['live_record_id'=>$record->id]]);
            $record->update(['metadata'=>[...$record->metadata,'media_ids'=>array_values(array_unique([...($record->metadata['media_ids']??[]),$media->id]))]]);
            return $media;
        });
        unlink($resolved);
    }
    public function configuration(): string
    {
        $hook=escapeshellarg(base_path('bin/live-hook.php'));
        $prefix='/opt/plesk/php/8.4/bin/php '.$hook;
        $secure=$this->secureIngestReady();
        $config=['logLevel'=>'warn','rtsp'=>false,'rtmp'=>true,'rtmpEncryption'=>$secure?'optional':'no','rtmpAddress'=>'127.0.0.1:1935','srt'=>false,'webrtc'=>false,'moq'=>false,'hls'=>true,'hlsAddress'=>'127.0.0.1:8888','hlsAlwaysRemux'=>true,'api'=>false,'playback'=>false,'authMethod'=>'http','authHTTPAddress'=>route('public.broadcast-auth'),'authHTTPExclude'=>[],
            'pathDefaults'=>['source'=>'publisher','overridePublisher'=>false,'record'=>true,'recordPath'=>rtrim(Storage::disk('live-recordings')->path(''),'/\\').'/%path/%Y-%m-%d_%H-%M-%S-%f','recordFormat'=>'fmp4','recordSegmentDuration'=>'1h','recordDeleteAfter'=>'0s','runOnAvailable'=>$prefix.' ready','runOnAvailableRestart'=>true,'runOnUnavailable'=>$prefix.' ended','runOnRecordSegmentComplete'=>$prefix.' recording'],
            'paths'=>['~^live$'=>['source'=>'publisher'],'~^live-[1-9][0-9]*$'=>['source'=>'publisher']]];
        if ($secure) {
            $config['rtmpsAddress']=':'.(int) config('platform.live_rtmp_port');
            $config['rtmpServerCert']=(string) config('platform.live_rtmp_cert');
            $config['rtmpServerKey']=(string) config('platform.live_rtmp_key');
        }
        $settings=app(Settings::class);
        if($settings->hasSecret('live_control')){
            $config['api']=true;$config['apiAddress']='127.0.0.1:9997';$config['apiAllowOrigins']=[];
        }
        if($settings->get('live_browser_enabled',false)){
            $config['webrtc']=true;$config['webrtcAddress']='127.0.0.1:8889';$config['webrtcAllowOrigins']=[];
            $config['webrtcLocalUDPAddress']=':8189';$config['webrtcLocalTCPAddress']=':8189';
            $config['webrtcIPsFromInterfaces']=false;$config['webrtcAdditionalHosts']=[$settings->get('live_browser_host',config('platform.live_rtmp_host'))];
            $config['rtsp']=true;$config['rtspAddress']='127.0.0.1:8554';$config['rtspTransports']=['tcp'];
            $config['paths']['~^browser-[a-f0-9-]{36}$']=['source'=>'publisher','record'=>false,
                'runOnAvailable'=>$prefix.' browser','runOnAvailableRestart'=>true,'runOnUnavailable'=>'','runOnRecordSegmentComplete'=>''];
        }
        // JSON is a YAML subset supported by MediaMTX; no second parser/dependency.
        return json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    }

    private function activeRecord(): ?SourceRecord
    {
        return SourceRecord::where('metadata->public_section','live')
            ->where('metadata->live_ingest_active',true)->where(function($query){$query->whereNull('metadata->live_status')->orWhere('metadata->live_status','!=','ended');})->first();
    }

    private function recordingRecord(): ?SourceRecord
    {
        return $this->activeRecord() ?? SourceRecord::where('metadata->public_section','live')
            ->where('metadata->live_stream_enabled',true)->where('metadata->live_recording_pending',true)
            ->latest('metadata->live_signal_at')->first();
    }

    private function candidateRecord(): ?SourceRecord
    {
        $records=SourceRecord::where('metadata->public_section','live')->where('metadata->live_stream_enabled',true)
            ->where(function($query){$query->whereNull('metadata->live_status')->orWhereIn('metadata->live_status',['draft','scheduled','live']);})->get();
        return $records->sortBy(function(SourceRecord $record){
            $status=$record->metadata['live_status']??null;
            $starts=$record->metadata['starts_at']??null;
            $time=$starts?strtotime((string)$starts):PHP_INT_MAX;
            return [$status==='live'?0:($starts&&$time<=time()?1:2),$time,(int)$record->id];
        })->first();
    }

    private function activateSharedRecord(): ?SourceRecord
    {
        if ($active=$this->activeRecord()) return !empty($active->metadata['live_stream_enabled'])?$active:null;
        $record=$this->candidateRecord();
        if (!$record) return null;
        $record->update(['metadata'=>[...$record->metadata,'live_ingest_active'=>true]]);
        return $record->fresh();
    }
}
