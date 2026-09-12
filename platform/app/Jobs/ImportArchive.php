<?php
namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\Importing\ImportCenter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
class ImportArchive implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(private string $runId){}
    public function handle(ImportCenter $center):void
    {
        $run = ImportRun::findOrFail($this->runId);
        $center->run($run);
    }
}
