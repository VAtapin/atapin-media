<?php
// Generated browser-test media only; never use a customer database.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.default')!=='sqlite')throw new RuntimeException('Testing SQLite required.');
if(($argv[1]??'')==='cleanup') {
    App\Models\SourceRecord::where('source','static-media-test')->delete();
    exit;
}
$source=realpath($argv[1]??'');
$root=realpath(__DIR__.'/artifacts');
if(!$source||!$root||!str_starts_with($source,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Generated test artifact required.');
$user=App\Models\User::firstOrCreate(['email'=>'static-media-test@example.com'],['name'=>'Static media test','password'=>'test-only']);
$file=new Illuminate\Http\UploadedFile($source,basename($source),null,null,true);
$media=app(App\Services\MediaLibrary::class)->upload($file,$user->id);
$record=App\Models\SourceRecord::create(['source'=>'static-media-test','source_id'=>(string)Illuminate\Support\Str::uuid(),'kind'=>'video','title'=>'Generated static playback test','body'=>'Isolated browser test','status'=>'ready','metadata'=>['public_published'=>true,'media_ids'=>[$media->id]]]);
echo json_encode(['page'=>parse_url(app(App\Services\PublicContent::class)->card($record)['url'],PHP_URL_PATH),'url'=>$media->publicUrl(),'file'=>Illuminate\Support\Facades\Storage::disk($media->disk)->path($media->path),'mime'=>$media->mime],JSON_THROW_ON_ERROR);
