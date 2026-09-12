<?php

namespace App\Services\Importing;

use App\Models\ImportRun;
use Symfony\Component\Process\Process;

class ImportProgress
{
    public function checkpoint(ImportRun $run, ?string $stage = null): void
    {
        $status = ImportRun::whereKey($run->id)->value('status');
        if (in_array($status, ['stop_requested', 'cancelled'], true)) throw new ImportStopped;
        if ($stage !== null && ($run->progress['stage'] ?? null) !== $stage) {
            $progress = ['stage' => $stage];
            ImportRun::whereKey($run->id)->whereNotIn('status', ['stop_requested', 'cancelled'])->update(['progress' => json_encode($progress)]);
            $run->progress = $progress;
        }
    }

    public function download(ImportRun $run, Process $process): void
    {
        $this->checkpoint($run, 'download');
        try {
            $process->start();
            while ($process->isRunning()) {
                $this->checkpoint($run);
                $process->checkTimeout();
                usleep(1000000);
            }
            $process->wait();
            $this->checkpoint($run);
        } finally {
            if ($process->isRunning()) $process->stop(1);
        }
    }
}
