<?php

// Entry point for Plesk's "Run a PHP script" tasks. No shell commands required.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$mode = $argv[1] ?? '';
if (count($argv) !== 2 || !in_array($mode, ['schedule', 'queue'], true)) {
    fwrite(STDERR, "Arguments: schedule OR queue\n");
    exit(64);
}

umask(0077);
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lock = fopen($app->storagePath('framework/cron-'.$mode.'.lock'), 'c');
if ($lock === false) {
    fwrite(STDERR, "Cannot open the scheduled-task lock. Check storage permissions.\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB, $wouldBlock)) {
    fclose($lock);
    if ($wouldBlock) {
        if ($mode === 'queue') {
            try { $app->make(\App\Services\Importing\ImportWorkerActivity::class)->observe(true); }
            catch (\Throwable $error) { report($error); } // Optional telemetry must not affect queue locking.
        }
        fwrite(STDOUT, "The previous task is still running; this tick was skipped.\n");
        exit(0);
    }
    fwrite(STDERR, "Cannot lock the scheduled task.\n");
    exit(1);
}

try {
    $arguments = $mode === 'schedule'
        ? ['command' => 'schedule:run']
        : ['command' => 'queue:work', '--stop-when-empty' => true,
            '--max-time' => 50, '--tries' => 3, '--timeout' => 3600];
    $input = new \Symfony\Component\Console\Input\ArrayInput($arguments);
    $input->setInteractive(false);
    $status = $kernel->handle($input, new \Symfony\Component\Console\Output\ConsoleOutput);
    $kernel->terminate($input, $status);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
exit($status);
