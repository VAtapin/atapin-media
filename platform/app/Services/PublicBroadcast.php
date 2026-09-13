<?php
namespace App\Services;
use App\Models\{SourceRecord,Media};
use Illuminate\Support\Facades\{DB,Storage};
class PublicBroadcast
{
    public function secureIngestReady(): bool
    {
        return is_readable((string) config('platform.live_rtmp_cert'))
            && is_readable((string) config('platform.live_rtmp_key'));
    }

    public function ingest(SourceRecord $record, ?string $key): array
    {
        $host = (string) config('platform.live_rtmp_host');
        $port = (int) config('platform.live_rtmp_port');
        $url = 'rtmps://'.$host.':'.$port.'/live-'.$record->id.'?user=publisher&pass='.rawurlencode((string) $key);

        return ['configured' => $this->secureIngestReady(), 'url' => $url, 'host' => $host, 'port' => $port];
    }

    public function record(string $path): ?SourceRecord
    {
        if(!preg_match('/^live-([1-9][0-9]*)$/D',$path,$match))return null;
        return SourceRecord::whereKey($match[1])->where('metadata->public_section','live')->where('metadata->live_stream_enabled',true)->first();
    }
    public function authorize(array $data): bool
    {
        $record=$this->record($data['path']??'');if(!$record)return false;
        if(($data['action']??'')==='read')return ($data['protocol']??'')==='hls'&&app(PublicContent::class)->visible($record);
        if(($data['action']??'')!=='publish'||($data['user']??'')!=='publisher')return false;
        $key=app(Settings::class)->secret('live_publish_'.$record->id);
        return is_string($key)&&is_string($data['password']??null)&&hash_equals($key,$data['password']);
    }
    public function signal(string $path,bool $ready): void
    {
        $record=$this->record($path);if(!$record)return;
        DB::transaction(function()use($record,$ready){
            $record=SourceRecord::lockForUpdate()->findOrFail($record->id);
            $record->update(['metadata'=>[...$record->metadata,'live_status'=>$ready?'live':'ended','live_signal_at'=>now()->toIso8601String()]]);
        });
    }
    public function recording(string $path,string $file): void
    {
        $record=$this->record($path);if(!$record)return;
        $root=Storage::disk('live-recordings')->path('');
        $resolved=\App\Services\Importing\ImportPath::resolve($root,$file);
        if(!is_file($resolved)||is_link($file)||strtolower(pathinfo($resolved,PATHINFO_EXTENSION))!=='mp4')throw new \RuntimeException('Invalid completed recording.');
        $relative=str_replace('\\','/',substr($resolved,strlen(rtrim(realpath($root),'/\\'))+1));
        if(!str_starts_with($relative,$path.'/'))throw new \RuntimeException('Recording belongs to another stream.');
        DB::transaction(function()use($record,$resolved,$relative){
            $record=SourceRecord::lockForUpdate()->findOrFail($record->id);
            $media=Media::firstOrCreate(['source'=>'live','source_id'=>hash('sha256',$relative)],['disk'=>'live-recordings','path'=>$relative,'kind'=>'video','mime'=>'video/mp4','bytes'=>filesize($resolved),'title'=>$record->title,'original_name'=>basename($resolved),'status'=>'unsorted','metadata'=>['live_record_id'=>$record->id]]);
            $record->update(['metadata'=>[...$record->metadata,'media_ids'=>array_values(array_unique([...($record->metadata['media_ids']??[]),$media->id]))]]);
        });
    }
    public function configuration(): string
    {
        $hook=escapeshellarg(base_path('bin/live-hook.php'));
        $prefix='/opt/plesk/php/8.4/bin/php '.$hook;
        $secure=$this->secureIngestReady();
        $config=['logLevel'=>'warn','rtsp'=>false,'rtmp'=>true,'rtmpEncryption'=>$secure?'optional':'no','rtmpAddress'=>'127.0.0.1:1935','srt'=>false,'webrtc'=>false,'moq'=>false,'hls'=>true,'hlsAddress'=>'127.0.0.1:8888','hlsAlwaysRemux'=>true,'api'=>false,'playback'=>false,'authMethod'=>'http','authHTTPAddress'=>route('public.broadcast-auth'),'authHTTPExclude'=>[],
            'pathDefaults'=>['source'=>'publisher','overridePublisher'=>false,'record'=>true,'recordPath'=>rtrim(Storage::disk('live-recordings')->path(''),'/\\').'/%path/%Y-%m-%d_%H-%M-%S-%f','recordFormat'=>'fmp4','recordSegmentDuration'=>'1h','recordDeleteAfter'=>'0s','runOnReady'=>$prefix.' ready','runOnNotReady'=>$prefix.' ended','runOnRecordSegmentComplete'=>$prefix.' recording'],
            'paths'=>['~^live-[1-9][0-9]*$'=>['source'=>'publisher']]];
        if ($secure) {
            $config['rtmpsAddress']=':'.(int) config('platform.live_rtmp_port');
            $config['rtmpServerCert']=(string) config('platform.live_rtmp_cert');
            $config['rtmpServerKey']=(string) config('platform.live_rtmp_key');
        }
        // JSON is a YAML subset supported by MediaMTX; no second parser/dependency.
        return json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    }
}
