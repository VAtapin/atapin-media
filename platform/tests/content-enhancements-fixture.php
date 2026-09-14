<?php
// Isolated browser data only; never use a customer database.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (PHP_SAPI!=='cli' || !$app->environment('testing') || config('database.default')!=='sqlite' || !str_contains(config('database.connections.sqlite.database'),'content-enhancements-')) throw new RuntimeException('Isolated testing database required.');
app(App\Services\Access::class)->seed();
$user=App\Models\User::firstOrCreate(['email'=>'test@example.com'],['name'=>'Redaktion','password'=>'kurz5']);
$user->roles()->syncWithoutDetaching(App\Models\Role::where('name','Owner')->firstOrFail());
$member=App\Models\User::firstOrCreate(['email'=>'viewer@example.com'],['name'=>'Testzuschauer','password'=>'kurz5']);
$member->forceFill(['email_verified_at'=>now()])->save();
app(App\Services\Settings::class)->update(['public_author_name'=>'Testautor','ai_enabled'=>false]);
$disk=Illuminate\Support\Facades\Storage::disk('media-canonical');
$disk->put('enhancements-browser.mp4','Not a playback fixture; only player DOM continuity is tested.');
$video=App\Models\Media::firstOrCreate(['source'=>'enhancements-browser','source_id'=>'video'],['title'=>'Video','kind'=>'video','mime'=>'video/mp4','disk'=>'media-canonical','path'=>'enhancements-browser.mp4','original_name'=>'enhancements-browser.mp4','bytes'=>64,'status'=>'ready']);
$path=public_path(ltrim(config('public_ui.hero_image'),'/'));
$mime=mime_content_type($path);
$cover=app(App\Services\MediaDerivatives::class)->register($path,$mime,'image','Browser cover',$video);
$record=App\Models\SourceRecord::updateOrCreate(['source'=>'enhancements-browser','source_id'=>'record'],['kind'=>'video','title'=>'Hoffnung in stürmischen Zeiten','body'=>'Originalbeschreibung mit Werbung und weiteren Details.','status'=>'ready','metadata'=>['public_published'=>true,'public_homepage'=>true,'public_section'=>'videos','duration'=>300,'short_description'=>'Gottes Zusagen schenken uns Hoffnung und Kraft im Alltag.','short_description_origin'=>'manual','media_ids'=>[$video->id,$cover->id],'cover_media_id'=>$cover->id]]);
$podcast=App\Models\SourceRecord::updateOrCreate(['source'=>'enhancements-browser','source_id'=>'podcast'],['kind'=>'video','title'=>'Podcast Browser','body'=>'Beschreibung','status'=>'ready','metadata'=>['public_section'=>'podcast','media_ids'=>[$video->id,$cover->id]]]);
echo json_encode(['id'=>$record->id,'podcast_id'=>$podcast->id,'url'=>parse_url(app(App\Services\PublicContent::class)->card($record)['url'],PHP_URL_PATH)],JSON_THROW_ON_ERROR);
