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
    public int $timeout = 3600;
    public int $tries = 1;
    public bool $failOnTimeout = true;
    public function __construct(private string $runId){}
    public function handle(ImportCenter $center):void
    {
        $run = ImportRun::findOrFail($this->runId);
        $center->run($run);
    }
    public function failed(?\Throwable $exception): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($exception) {
            $run = ImportRun::lockForUpdate()->find($this->runId);
            if (! $run || ! in_array($run->status, ['running', 'stop_requested'], true)) return;
            $cancelled = $run->status === 'stop_requested';
            $run->update(['status' => $cancelled ? 'cancelled' : 'failed', 'finished_at' => now(),
                'error' => $cancelled ? null : ($exception?->getMessage() ?: __('imports.worker_stopped'))]);
        });
    }
}
