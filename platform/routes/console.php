<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Access;

Artisan::command('platform:access', function () { app(Access::class)->seed(); $this->info('Roles and permissions ready.'); });
Schedule::command('queue:prune-failed --hours=720')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
