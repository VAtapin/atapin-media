<?php
namespace Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;

class CronMarkerJob implements ShouldQueue
{
    public function handle(): void
    {
        file_put_contents(storage_path('framework/testing-cron-marker'), 'processed');
    }
}
