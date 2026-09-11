<?php
namespace App\Jobs;
use App\Models\ImportRun;
use App\Services\ArchiveImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
class ImportArchive implements ShouldQueue
{
    use Queueable;
    public int $timeout=3500;
    public int $tries=3;
    public function __construct(public string $runId){}
    public function middleware():array{return [(new WithoutOverlapping('archive-import'))->releaseAfter(120)->expireAfter(3600)];}
    public function handle(ArchiveImporter $importer):void{$importer->import(ImportRun::findOrFail($this->runId));}
    public function failed(?\Throwable $error):void{ImportRun::whereKey($this->runId)->update(['status'=>'failed','notes'=>json_encode(['Import failed. Check the application log.'])]);}
}
