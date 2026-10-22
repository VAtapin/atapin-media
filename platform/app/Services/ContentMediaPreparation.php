<?php
namespace App\Services;
use App\Models\SourceRecord;
use App\Jobs\PrepareContentMedia;
class ContentMediaPreparation
{
    public function queueMissing(SourceRecord $record,bool $hlsOnly=false): int
    {
        if(!in_array($record->kind,['video','short','post'],true)||($record->metadata['archive_data']??false)||$record->trashed())return 0;
        $media=app(MediaDerivatives::class);if(!$media->video($record))return 0;
        $operations=[];
        $hls=$record->metadata['hls_playback']??[];$video=$media->video($record);
        if(($record->metadata['public_published']??false)&&(($hls['state']??null)!=='ready'||($hls['source_media_id']??null)!==$video->id))$operations[]='hls';
        if(!$hlsOnly){if(!$media->hasCover($record))$operations[]='frame';if(($record->metadata['public_section']??null)==='podcast'&&!($record->metadata['podcast_audio_id']??null))$operations[]='podcast';}
        $count=0;
        foreach($operations as $operation){
            $job=$record->metadata['media_jobs'][$operation]??[];$state=$job['state']??null;
            if($state==='completed'||(in_array($state,['queued','running'],true)&&strtotime($job['updated_at']??'now')>time()-7200))continue;
            $meta=$record->metadata;$meta['media_jobs'][$operation]=['state'=>'queued','updated_at'=>now()->toIso8601String()];
            $record->updateQuietly(['metadata'=>$meta]);dispatch((new PrepareContentMedia((int)$record->id,$operation))->afterCommit());$count++;
        }
        return $count;
    }
}
