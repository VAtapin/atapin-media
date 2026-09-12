<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app(\App\Services\Access::class)->seed();
$user=\App\Models\User::create(['name'=>'Redaktion','email'=>'test@example.com','password'=>'kurz5']);
$user->roles()->attach(\App\Models\Role::where('name','Owner')->firstOrFail());
\App\Models\SourceRecord::create(['source'=>'youtube','source_id'=>'browser-video','kind'=>'video','title'=>'Browser playlist video','body'=>'Original description','status'=>'unsorted','metadata'=>[]]);
app(\App\Services\Importing\ContentMetadataImporter::class)->playlist('youtube',['id'=>'PLbrowser','title'=>'Browser playlist','ordered_items'=>[
    ['position'=>1,'id'=>'browser-video','title'=>'Browser playlist video'],['position'=>2,'id'=>null,'title'=>'Unavailable video'],
]]);
