<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$broadcast=$app->make(App\Services\PublicBroadcast::class);
try {
    $path=getenv('MTX_PATH')?:'';
    if(($argv[1]??'')==='recording')$broadcast->recording($path,getenv('MTX_SEGMENT_PATH')?:'');
    elseif(in_array($argv[1]??'',['ready','ended'],true))$broadcast->signal($path,$argv[1]==='ready');
    else exit(1);
}catch(Throwable){fwrite(STDERR,"Live hook failed; check platform configuration.\n");exit(1);}
