<?php

// Run against the isolated CI database, never against an installed site.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Queue;

function runCron(string $mode, int $expected = 0): string
{
    // Plesk need not start in the application's directory.
    $process = new Process([PHP_BINARY, base_path('bin/cron.php'), $mode], sys_get_temp_dir());
    $process->setTimeout(20);
    if ($process->run() !== $expected) throw new RuntimeException($process->getErrorOutput().$process->getOutput());
    return $process->getOutput();
}

runCron('invalid', 64);
runCron('schedule');
$lock = fopen(storage_path('framework/cron-queue.lock'), 'c');
flock($lock, LOCK_EX);
try {
    if (!str_contains(runCron('queue'), 'skipped')) throw new RuntimeException('Concurrent tick was not skipped.');
    if (PHP_OS_FAMILY === 'Linux' && config('cache.default') === 'file') {
        $measurement = \Illuminate\Support\Facades\Cache::get('import-worker-observation:'.hash('sha256', base_path()).':scheduled');
        if (!$measurement || in_array($measurement['public']['state'] ?? null, ['observed','working'], true)) throw new RuntimeException('A skipped observer tick must not be reported as a worker.');
    }
} finally { flock($lock, LOCK_UN); fclose($lock); }

Queue::push(new \Tests\Fixtures\CronMarkerJob);
runCron('queue');
$marker = storage_path('framework/testing-cron-marker');
if (!is_file($marker) || file_get_contents($marker) !== 'processed') throw new RuntimeException('Queued job did not run.');
unlink($marker);
runCron('queue');
echo "Plesk PHP tasks: scheduler, exclusive lock, queued job, empty queue and arbitrary working directory OK.\n";
