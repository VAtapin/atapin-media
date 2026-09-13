<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Access;

Artisan::command('platform:access', function () { app(Access::class)->seed(); $this->info('Roles and permissions ready.'); });
Schedule::command('queue:prune-failed --hours=720')->daily();
Artisan::command('public:live-reminders',function(){
    $service=app(\App\Services\PublicLiveReminders::class);
    if(!$service->mailReady()){$this->warn('Email delivery is not configured; no reminders were dispatched.');return;}
    $this->info('Due reminder candidates: '.$service->dispatchDue());
});
Schedule::command('public:live-reminders')->everyMinute()->withoutOverlapping();
Artisan::command('public:push-key',function(){
    $settings=app(\App\Services\Settings::class);
    if(app(\App\Services\PublicPush::class)->ready()){$this->info('Existing push keys preserved.');return;}
    if($settings->get('push_public_key')||$settings->hasSecret('push_private_key')){$this->error('Incomplete key pair: refusing automatic replacement.');return 1;}
    $keys=\Minishlink\WebPush\VAPID::createVapidKeys();
    \Illuminate\Support\Facades\DB::transaction(function()use($settings,$keys){$settings->update(['push_public_key'=>$keys['publicKey']]);$settings->updateSecrets(['push_private_key'=>$keys['privateKey']]);});
    $this->info('Push key pair stored privately.');
});
Artisan::command('public:push-reminders',function(){$this->info('Due push candidates: '.app(\App\Services\PublicPush::class)->dispatchDue());});
Schedule::command('public:push-reminders')->everyMinute()->withoutOverlapping();
Artisan::command('public:live-config',function(){
    $root=dirname(base_path(),2).'/private/atapin-live';
    if(!is_dir($root))mkdir($root,0700,true);
    if(!is_dir($root.'/recordings'))mkdir($root.'/recordings',0700,true);
    file_put_contents($root.'/mediamtx.yml',app(\App\Services\PublicBroadcast::class)->configuration());chmod($root.'/mediamtx.yml',0600);
    $broadcast=app(\App\Services\PublicBroadcast::class);
    $this->info('MediaMTX private configuration generated.');
    if($broadcast->secureIngestReady())$this->info('Public RTMPS ingest is enabled on port '.config('platform.live_rtmp_port').'.');
    else $this->warn('RTMPS certificate/key are not readable; only the local SSH fallback is enabled.');
});
Artisan::command('public:live-presence-prune',function(){\Illuminate\Support\Facades\DB::table('public_live_presence')->where('seen_at','<',now()->subDay())->delete();});
Schedule::command('public:live-presence-prune')->daily()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
