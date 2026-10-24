<?php
namespace App\Jobs;

use App\Models\SourceRecord;
use App\Services\MediaDerivatives;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};

class PrepareContentMedia implements ShouldQueue,ShouldBeUnique
{
    use Queueable;
    public int $tries=1;
    public int $timeout=3400;
    public int $uniqueFor=3600;
    public function __construct(public int $id,public string $operation='frame',public bool $bypassHlsPause=false) {}
    public function uniqueId(): string { return $this->id.':'.$this->operation.($this->bypassHlsPause?':targeted':''); }
    public function handle(MediaDerivatives $media): void
    {
        $record=SourceRecord::find($this->id);if(!$record)return;
        if($this->operation==='podcast' && ($record->metadata['public_section']??null)!=='podcast')return;
        if($this->operation==='hls'&&!$this->bypassHlsPause&&\Illuminate\Support\Facades\Cache::get('media.hls.paused')){$this->state('paused');return;}
        $this->state('running');
        try {
            if($this->operation==='podcast')$media->podcast($record);
            elseif($this->operation==='hls'){$url=$media->hls($record);$this->hlsReady($record,$url);}
            else $media->frame($record);
            $this->state('completed');
        } catch (\Throwable $error) {
            $this->stopHlsAfterFailure($error);
            $this->state('failed',$error);
            app(\App\Services\Audit::class)->record('content.media.failed',(string)$this->id,['operation'=>$this->operation,'error_type'=>$error::class,'error'=>mb_substr($error->getMessage(),0,500)]);
        }
    }
    public function failed(?\Throwable $error): void { $this->state('failed',$error);$this->stopHlsAfterFailure($error); }
    private function stopHlsAfterFailure(?\Throwable $error): void
    {
        if($this->operation==='hls')\Illuminate\Support\Facades\Cache::forever('media.hls.paused',['source_record_id'=>$this->id,'error_type'=>$error?$error::class:null,'error'=>$error?mb_substr($error->getMessage(),0,500):null,'at'=>now()->toIso8601String()]);
    }
    private function state(string $state,?\Throwable $error=null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function()use($state,$error){
            $record=SourceRecord::lockForUpdate()->find($this->id);if(!$record)return;
            $meta=$record->metadata; $meta['media_jobs'][$this->operation]=['state'=>$state,'updated_at'=>now()->toIso8601String()];
            if($error)$meta['media_jobs'][$this->operation]['error']=mb_substr($error->getMessage(),0,500);
            if($this->operation==='hls'&&$state!=='completed'&&($meta['hls_playback']['state']??null)!=='ready')$meta['hls_playback']=['state'=>$state,'source_media_id'=>app(MediaDerivatives::class)->video($record)?->id,'updated_at'=>now()->toIso8601String()];
            $record->updateQuietly(['metadata'=>$meta]);
        });
    }
    private function hlsReady(SourceRecord $record,string $url): void
    {
        \Illuminate\Support\Facades\DB::transaction(function()use($record,$url){
            $current=SourceRecord::lockForUpdate()->find($record->id);if(!$current)return;
            $video=app(MediaDerivatives::class)->video($current);if(!$video)return;
            $meta=$current->metadata;$meta['hls_playback']=['state'=>'ready','url'=>$url,'fallback_url'=>str_replace('/index.m3u8','/playback.mp4',$url),'source_media_id'=>$video->id,'updated_at'=>now()->toIso8601String()];
            $current->updateQuietly(['metadata'=>$meta]);
        });
    }
}
