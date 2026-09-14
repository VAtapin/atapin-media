<?php
namespace App\Console\Commands;
use App\Models\SourceRecord;
use Illuminate\Console\Command;
class PrepareMissingContentMedia extends Command
{
    protected $signature='media:prepare-missing';
    protected $description='Queue free local cover/audio extraction for existing content; never calls AI or rewrites originals.';
    public function handle(\App\Services\ContentMediaPreparation $preparation): int
    {
        $count=0;
        SourceRecord::whereIn('kind',['video','short','post'])->where('source','!=','catalog-reset')->chunkById(100,function($records)use($preparation,&$count){foreach($records as $record)$count+=$preparation->queueMissing($record);});
        $this->info('Queued '.$count.' local media preparations.');return self::SUCCESS;
    }
}
