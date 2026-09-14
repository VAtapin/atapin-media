<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\InstallationHealth;
class CheckInstallation extends Command
{
    protected $signature='platform:check';
    protected $description='Check runtime, SQL, storage, cache, search, owner, queue and scheduled tasks';
    public function handle(InstallationHealth $health): int
    {
        $report = $health->report();
        foreach ($report['checks'] as $name => $check) {
            $message = ucfirst(str_replace('_', ' ', $name)).': '.strtoupper($check['status']);
            if (isset($check['pending'], $check['failed'])) $message .= ' (queued '.$check['pending'].', failed '.$check['failed'].')';
            if (isset($check['driver'])) $message .= ' ['.$check['driver'].']';
            $check['status'] === 'failed' ? $this->error($message) : $this->line($message);
        }

        return $report['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
