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
    public int $timeout=3500;
    public int $uniqueFor=3600;
    public function __construct(public int $id,public string $operation='frame') {}
    public function uniqueId(): string { return $this->id.':'.$this->operation; }
    public function handle(MediaDerivatives $media): void
    {
        $record=SourceRecord::find($this->id);if(!$record)return;
        if($this->operation==='podcast' && ($record->metadata['public_section']??null)!=='podcast')return;
        $this->state('running');
        try {
            if($this->operation==='podcast')$media->podcast($record);
            elseif($this->operation==='hls'){$url=$media->hls($record);$this->hlsReady($record,$url);}
            else $media->frame($record);
            $this->state('completed');
        } catch (\Throwable $error) {
            $this->state('failed');
            app(\App\Services\Audit::class)->record('content.media.failed',(string)$this->id,['operation'=>$this->operation,'error_type'=>$error::class]);
        }
    }
    public function failed(?\Throwable $error): void { $this->state('failed'); }
    private function state(string $state): void
    {
        \Illuminate\Support\Facades\DB::transaction(function()use($state){
            $record=SourceRecord::lockForUpdate()->find($this->id);if(!$record)return;
            $meta=$record->metadata; $meta['media_jobs'][$this->operation]=['state'=>$state,'updated_at'=>now()->toIso8601String()];
            if($this->operation==='hls'&&$state!=='completed')$meta['hls_playback']=['state'=>$state,'source_media_id'=>app(MediaDerivatives::class)->video($record)?->id,'updated_at'=>now()->toIso8601String()];
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
