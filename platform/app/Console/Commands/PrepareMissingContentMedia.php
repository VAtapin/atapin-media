<?php
namespace App\Console\Commands;
use App\Models\SourceRecord;
use Illuminate\Console\Command;
class PrepareMissingContentMedia extends Command
{
    protected $signature='media:prepare-missing {--hls-only : Queue HLS playback encoding only} {--retry-failed : Explicitly clear the HLS stop switch and retry failed HLS records} {--limit=0 : Maximum number of records to scan, or 0 for all}';
    protected $description='Queue missing public video HLS playback files and optional local media preparations.';
    public function handle(\App\Services\ContentMediaPreparation $preparation): int
    {
        $count=0;
        if($this->option('retry-failed'))\Illuminate\Support\Facades\Cache::forget('media.hls.paused');
        $limit=max(0,(int)$this->option('limit'));$scanned=0;
        $retryFailed=(bool)$this->option('retry-failed');
        SourceRecord::whereIn('kind',['video','short','post'])->where('source','!=','catalog-reset')->where('status','ready')->whereJsonContains('metadata->public_published',true)->chunkById(100,function($records)use($preparation,&$count,&$scanned,$limit,$retryFailed){foreach($records as $record){if($limit&&$scanned >= $limit)return false;$scanned++;$count+=$preparation->queueMissing($record,(bool)$this->option('hls-only'),$retryFailed);}});
        $this->info('Queued '.$count.' local media preparations.');return self::SUCCESS;
    }
}
