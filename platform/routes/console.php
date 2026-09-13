<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Access;

Artisan::command('platform:access', function () { app(Access::class)->seed(); $this->info('Roles and permissions ready.'); });
Schedule::command('queue:prune-failed --hours=720')->daily();
Artisan::command('public:live-reminders',function(){
    $service=app(\App\Services\PublicLiveReminders::class);
    if(!$service->mailReady()){$this->warn('SMTP delivery is not configured; no reminders were dispatched.');return;}
    $this->info('Due reminder candidates: '.$service->dispatchDue());
});
Schedule::command('public:live-reminders')->everyMinute()->withoutOverlapping();
Artisan::command('public:live-presence-prune',function(){\Illuminate\Support\Facades\DB::table('public_live_presence')->where('seen_at','<',now()->subDay())->delete();});
Schedule::command('public:live-presence-prune')->daily()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
