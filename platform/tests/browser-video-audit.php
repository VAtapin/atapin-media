<?php
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing')exit(1);
require dirname(__DIR__).'/vendor/autoload.php';$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$run=\App\Models\ImportRun::findOrFail($argv[1]??'');
if($run->source!=='local-video-check')exit(1);
app(\App\Services\Importing\ImportCenter::class)->run($run);
