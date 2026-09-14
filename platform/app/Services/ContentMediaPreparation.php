<?php
namespace App\Services;
use App\Models\SourceRecord;
use App\Jobs\PrepareContentMedia;
class ContentMediaPreparation
{
    public function queueMissing(SourceRecord $record): int
    {
        if(!in_array($record->kind,['video','short','post'],true)||($record->metadata['archive_data']??false)||$record->trashed())return 0;
        $media=app(MediaDerivatives::class);if(!$media->video($record))return 0;
        $operations=[];
        if(!$media->hasCover($record))$operations[]='frame';
        if(($record->metadata['public_section']??null)==='podcast'&&!($record->metadata['podcast_audio_id']??null))$operations[]='podcast';
        $count=0;
        foreach($operations as $operation){
            if(isset($record->metadata['media_jobs'][$operation]))continue;
            $meta=$record->metadata;$meta['media_jobs'][$operation]=['state'=>'queued','updated_at'=>now()->toIso8601String()];
            $record->updateQuietly(['metadata'=>$meta]);dispatch((new PrepareContentMedia((int)$record->id,$operation))->afterCommit());$count++;
        }
        return $count;
    }
}
