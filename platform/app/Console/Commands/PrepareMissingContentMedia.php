<?php
namespace App\Console\Commands;
use App\Models\SourceRecord;
use Illuminate\Console\Command;
class PrepareMissingContentMedia extends Command
{
    protected $signature='media:prepare-missing {--hls-only : Queue HLS playback encoding only} {--limit=0 : Maximum number of records to scan, or 0 for all}';
    protected $description='Queue missing public video HLS playback files and optional local media preparations.';
    public function handle(\App\Services\ContentMediaPreparation $preparation): int
    {
        $count=0;
        $limit=max(0,(int)$this->option('limit'));$scanned=0;
        SourceRecord::whereIn('kind',['video','short','post'])->where('source','!=','catalog-reset')->where('status','ready')->whereJsonContains('metadata->public_published',true)->chunkById(100,function($records)use($preparation,&$count,&$scanned,$limit){foreach($records as $record){if($limit&&$scanned >= $limit)return false;$scanned++;$count+=$preparation->queueMissing($record,(bool)$this->option('hls-only'));}});
        $this->info('Queued '.$count.' local media preparations.');return self::SUCCESS;
    }
}
