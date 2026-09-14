<?php
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'||getenv('DB_CONNECTION')!=='sqlite'||!str_contains((string)getenv('DB_DATABASE'),'desktop-workspaces-live'))exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['filesystems.disks.live-recordings.root'=>storage_path('app/testing-live-recordings')]);
app(\App\Services\Access::class)->seed();
$user=\App\Models\User::firstOrCreate(['email'=>'studio@example.test'],['name'=>'Studio Test','password'=>'test-only-password']);$user->roles()->syncWithoutDetaching(\App\Models\Role::where('name','Owner')->firstOrFail());
app(\App\Services\Settings::class)->updateSecrets(['live_control'=>bin2hex(random_bytes(32))]);
app(\App\Services\Settings::class)->update(['live_browser_enabled'=>true,'live_browser_host'=>'127.0.0.1']);
\App\Models\SourceRecord::firstOrCreate(['source'=>'website','source_id'=>'browser-live-test'],['title'=>'Browser studio test','kind'=>'video','status'=>'ready','metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true,'starts_at'=>now()->addMinutes(10)->toIso8601String()]]);
$config=json_decode(app(\App\Services\PublicBroadcast::class)->configuration(),true);
$config['logLevel']='info';
$config['authHTTPAddress']='http://127.0.0.1:8809/live/server-auth';
$config['webrtcAdditionalHosts']=['127.0.0.1'];$config['rtmp']=false;$config['rtsp']=false;$config['hls']=false;
// This transport integration test uses real MediaMTX/WebRTC, not Linux/Plesk hook processes.
$config['pathDefaults']['record']=false;
$config['pathDefaults']['runOnAvailable']='';$config['pathDefaults']['runOnUnavailable']='';$config['pathDefaults']['runOnRecordSegmentComplete']='';
$config['paths']['~^browser-[a-f0-9-]{36}$']['runOnAvailable']='';
if(!is_dir(__DIR__.'/artifacts'))mkdir(__DIR__.'/artifacts',0700,true);
file_put_contents(__DIR__.'/artifacts/browser-live-test.yml',json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
chmod(__DIR__.'/artifacts/browser-live-test.yml',0600);
