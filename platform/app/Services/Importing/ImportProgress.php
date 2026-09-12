<?php

namespace App\Services\Importing;

use App\Models\ImportRun;
use Symfony\Component\Process\Process;

class ImportProgress
{
    private static array $lastHeartbeat=[];
    public function checkpoint(ImportRun $run, ?string $stage = null, array $details = [], bool $force = false): void
    {
        $status = ImportRun::whereKey($run->id)->value('status');
        if (in_array($status, ['stop_requested', 'cancelled'], true)) throw new ImportStopped;
        $progress = $run->progress ?? [];
        if ($stage !== null && ($progress['stage'] ?? null) !== $stage) {
            unset($progress['file'], $progress['file_bytes'], $progress['file_total_bytes']);
            $progress['stage'] = $stage;
            $force = true;
        }
        $run->progress = array_merge($progress, $details);
        if ($force || now()->timestamp - (self::$lastHeartbeat[$run->id] ?? 0) >= 5) {
            $run->progress = array_merge($run->progress, ['activity_at' => now()->toIso8601String()]);
            ImportRun::whereKey($run->id)->where('status', 'running')->update(['progress' => json_encode($run->progress), 'updated_at' => now()]);
            self::$lastHeartbeat[$run->id] = now()->timestamp;
        }
    }

    public function hashFile(ImportRun $run, string $path, string $stage): string
    {
        $stream = fopen($path, 'rb');
        if (! $stream) throw new \RuntimeException('Cannot read original file.');
        $total = filesize($path);
        $bytes = 0;
        $hash = hash_init('sha256');
        try {
            $this->checkpoint($run, $stage, ['file' => basename($path), 'file_bytes' => 0, 'file_total_bytes' => $total], true);
            while (! feof($stream)) {
                $chunk = fread($stream, 8 * 1024 * 1024);
                if ($chunk === false) throw new \RuntimeException('Cannot read original file.');
                hash_update($hash, $chunk);
                $bytes += strlen($chunk);
                $this->checkpoint($run, null, ['file_bytes' => $bytes]);
            }
            $this->checkpoint($run, null, ['file_bytes' => $bytes], true);
            return hash_final($hash);
        } finally { fclose($stream); }
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
