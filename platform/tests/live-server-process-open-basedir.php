<?php
require dirname(__DIR__).'/vendor/autoload.php';

if(PHP_OS_FAMILY==='Linux'&&@fopen('/dev/null','c')!==false){
    fwrite(STDERR,"Test requires open_basedir to exclude /dev/null.\n");
    exit(1);
}

$command=PHP_OS_FAMILY==='Linux'?['/bin/echo','validated']:[PHP_BINARY,'-r','echo "validated";'];
$method=new ReflectionMethod(\App\Services\LiveServer::class,'runServerCommand');
$method->invoke(new \App\Services\LiveServer(),$command,5);
fwrite(STDOUT,"Live server command passed.\n");
