<?php
// Visual-only content. Never run against a customer database.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! str_contains(config('database.connections.sqlite.database'), 'public-pages-')) {
    throw new RuntimeException('An isolated public-pages testing SQLite database is required.');
}
if (App\Models\SourceRecord::where('source','public-visual-fixture')->exists()) {
    throw new RuntimeException('Fixture already exists; use a new isolated database.');
}
$paths=[];
$coverBytes=file_get_contents(public_path('assets/brand/owner/desktop/wallpapers/manna-mountains.png'));
$coverPath=hash('sha256',$coverBytes).'.png';
Illuminate\Support\Facades\Storage::disk('media-canonical')->put($coverPath,$coverBytes);
$cover=App\Models\Media::create(['title'=>'Isoliertes Kategoriebild','original_name'=>'manna-mountains.png',
    'kind'=>'image','mime'=>'image/png','bytes'=>strlen($coverBytes),'disk'=>'media-canonical','path'=>$coverPath,
    'source'=>'public-visual-fixture','source_id'=>'category-cover']);
$category=App\Models\TaxonomyTerm::create(['kind'=>'category','name'=>'Glaube & Leben','slug'=>'glaube-leben',
    'active'=>true,'cover_media_id'=>$cover->id]);
$topics=[
    App\Models\TaxonomyTerm::create(['kind'=>'topic','name'=>'Gebet','slug'=>'gebet','parent_id'=>$category->id,'active'=>true]),
    App\Models\TaxonomyTerm::create(['kind'=>'topic','name'=>'Hoffnung im Alltag','slug'=>'hoffnung-im-alltag','parent_id'=>$category->id,'active'=>true]),
];
$taxonomy=app(App\Services\Taxonomy::class);
foreach (['videos'=>['video',8],'beitraege'=>['post',6],'podcast'=>['post',4],'live'=>['video',3],'community'=>['post',4]] as $section=>[$kind,$count]) {
    for ($i=1;$i<=$count;$i++) {
        $record=App\Models\SourceRecord::create(['source'=>'public-visual-fixture','source_id'=>"$section-$i",'kind'=>$kind,'title'=>"Visuelle Testdaten $section $i — Hoffnung und Glaube im Alltag",'body'=>str_repeat('Nur isolierte Testdaten für die Prüfung der Darstellung. ',8),'status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>$section,'public_published_at'=>'2026-09-13','author'=>'Testautor','tags'=>['Gebet','Glaube'],'views'=>42,'duration'=>240,'live_status'=>$i===1?'live':($i===2?'scheduled':'ended'),'starts_at'=>'2027-01-01T12:00:00']]);
        if(in_array($section,['videos','beitraege'],true))$taxonomy->sync($record,[$topics[$i%2]->id]);
        if($i===1&&in_array($section,['videos','beitraege']))$paths[$section]=parse_url(app(App\Services\PublicContent::class)->card($record)['url'],PHP_URL_PATH);
    }
}
App\Models\SourceRecord::create(['source'=>'public-visual-fixture','source_id'=>'poll','kind'=>'poll','title'=>'Visuelle Testumfrage','body'=>'','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'community','poll'=>['options'=>[['text'=>'Gebet'],['text'=>'Gemeinschaft']]]]]);
for($i=1;$i<=5;$i++){
    $book=App\Models\Product::create(['title'=>"Testbuch $i — Hoffnung im Alltag",'description'=>str_repeat('Beschreibung aus der isolierten Datenbank. ',8),'price_cents'=>990,'currency'=>'EUR','status'=>'active']);
    $taxonomy->sync($book,[$topics[$i%2]->id]);
    if($i===1)$paths['buecher']=parse_url(app(App\Services\PublicBooks::class)->card($book)['url'],PHP_URL_PATH);
}
echo json_encode($paths,JSON_THROW_ON_ERROR);
