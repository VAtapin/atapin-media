<?php
namespace App\Jobs;
use App\Models\SourceRecord;
use App\Services\Importing\LocalMediaLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
class RepairLocalMediaLinks implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    public int $timeout=600;
    public int $tries=2;
    public int $uniqueFor=900;
    public function uniqueId(): string {return 'local-links';}
    public function handle(LocalMediaLinks $links): void
    {
        SourceRecord::whereIn('kind',['video','short','post'])->chunkById(100,function ($records) use ($links) {
            foreach ($records as $record) $links->repair($record);
        });
    }
}
