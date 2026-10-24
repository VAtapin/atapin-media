<?php
namespace App\Console\Commands;
use App\Models\SourceRecord;
use Illuminate\Console\Command;
class PrepareMissingContentMedia extends Command
{
    protected $signature='media:prepare-missing {--hls-only : Queue HLS playback encoding only} {--retry-failed : Explicitly clear the HLS stop switch and retry failed HLS records} {--include-unpublished : Also prepare local videos that are not published yet} {--limit=0 : Maximum number of records to scan, or 0 for all} {--record= : Queue HLS playback encoding for one record ID while preserving the global pause}';
    protected $description='Queue missing video HLS playback files and optional local media preparations.';
    public function handle(\App\Services\ContentMediaPreparation $preparation): int
    {
        $includeUnpublished=(bool)$this->option('include-unpublished');
        if($this->option('record'))return $this->queueOne((int)$this->option('record'),$includeUnpublished);
        $count=0;
        if($this->option('retry-failed'))\Illuminate\Support\Facades\Cache::forget('media.hls.paused');
        $limit=max(0,(int)$this->option('limit'));$scanned=0;
        $retryFailed=(bool)$this->option('retry-failed');
        $query=SourceRecord::whereIn('kind',['video','short','post'])->where('source','!=','catalog-reset')->where('status','ready');
        if(!$includeUnpublished)$query->whereJsonContains('metadata->public_published',true);
        $query->chunkById(100,function($records)use($preparation,&$count,&$scanned,$limit,$retryFailed,$includeUnpublished){foreach($records as $record){if($limit&&$scanned >= $limit)return false;$scanned++;$count+=$preparation->queueMissing($record,(bool)$this->option('hls-only'),$retryFailed,$includeUnpublished);}});
        $this->info('Queued '.$count.' local media preparations.');return self::SUCCESS;
    }
    private function queueOne(int $id,bool $includeUnpublished=false): int
    {
        $query=SourceRecord::whereIn('kind',['video','short','post'])->where('source','!=','catalog-reset')->where('status','ready');
        if(!$includeUnpublished)$query->whereJsonContains('metadata->public_published',true);
        $record=$query->find($id);
        if(!$record){$this->error($includeUnpublished?'Record is not an eligible video.':'Record is not an eligible published video.');return self::FAILURE;}
        $video=app(\App\Services\MediaDerivatives::class)->video($record);
        if(!$video){$this->error('Record has no available local video.');return self::FAILURE;}
        $playback=$record->metadata['hls_playback']??[];
        if(($playback['state']??null)==='ready'&&($playback['source_media_id']??null)===$video->id&&($playback['profile_version']??null)===\App\Services\MediaDerivatives::HLS_PROFILE_VERSION){$this->info('HLS is already ready for record '.$id.'.');return self::SUCCESS;}
        $meta=$record->metadata;$meta['media_jobs']['hls']=['state'=>'queued','updated_at'=>now()->toIso8601String()];$record->updateQuietly(['metadata'=>$meta]);
        dispatch((new \App\Jobs\PrepareContentMedia($id,'hls',true,(bool)($record->metadata['public_published']??false)))->afterCommit());
        $this->info('Queued HLS preparation for record '.$id.'; the global HLS pause remains active.');return self::SUCCESS;
    }
}
