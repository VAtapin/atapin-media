<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app(\App\Services\Access::class)->seed();
$user=\App\Models\User::firstOrCreate(['email'=>'test@example.com'],['name'=>'Redaktion','password'=>'kurz5']);
$user->roles()->syncWithoutDetaching(\App\Models\Role::where('name','Owner')->firstOrFail());
$inbox = config('platform.import_inbox_root');
if (!is_dir($inbox.'/browser-folder')) mkdir($inbox.'/browser-folder', 0700, true);
file_put_contents($inbox.'/browser-folder/inside.txt', 'Browser folder original');
$record=app(\App\Services\Importing\ContentMetadataImporter::class)->record('youtube','browser-video','video','Browser playlist video','Original description',[]);
$record->update(['title'=>'Browser playlist video','body'=>'Original description','status'=>'unsorted']);
app(\App\Services\Importing\ContentMetadataImporter::class)->playlist('youtube',['id'=>'PLbrowser','title'=>'Browser playlist','ordered_items'=>[
    ['position'=>1,'id'=>'browser-video','title'=>'Browser playlist video'],['position'=>2,'id'=>null,'title'=>'Unavailable video'],
]]);
