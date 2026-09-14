<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (PHP_SAPI!=='cli'||!$app->environment('testing')||config('database.default')!=='sqlite'||!str_contains(config('database.connections.sqlite.database'),'public-live-browser-')) throw new RuntimeException('Isolated testing database required.');
$record=App\Models\SourceRecord::create(['source'=>'website','source_id'=>'browser-current','kind'=>'video','title'=>'Aktueller Livestream mit langem Titel','status'=>'ready',
    'metadata'=>['public_section'=>'live','public_published'=>true,'live_stream_enabled'=>true,'live_status'=>'live','starts_at'=>now()->toIso8601String()]]);
echo $record->id;
