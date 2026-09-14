<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$broadcast=$app->make(App\Services\PublicBroadcast::class);
try {
    $path=getenv('MTX_PATH')?:'';
    if(($argv[1]??'')==='recording')$broadcast->recording($path,getenv('MTX_SEGMENT_PATH')?:'');
    elseif(in_array($argv[1]??'',['ready','ended'],true)) {
        $record = $broadcast->record($path);
        $broadcast->signal($path,$argv[1]==='ready');
        if (($argv[1]??'') === 'ready') $app->make(App\Services\Publishing\YouTubeLiveRelay::class)->run($path);
        elseif ($record) $app->make(App\Services\Publishing\PublishingService::class)->queueLiveCompletion($record);
    }
    else exit(1);
}catch(Throwable){fwrite(STDERR,"Live hook failed; check platform configuration.\n");exit(1);}
