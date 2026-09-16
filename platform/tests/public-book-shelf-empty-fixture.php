<?php
// Visual-only fixture for the shelf's zero-book state.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_contains(config('database.connections.sqlite.database'), 'public-pages-shelf-empty-')) {
    throw new RuntimeException('A dedicated shelf-empty testing SQLite database is required.');
}
if (App\Models\TaxonomyTerm::query()->exists()) {
    throw new RuntimeException('Fixture already exists; use a new isolated database.');
}
App\Models\TaxonomyTerm::create(['kind'=>'category','name'=>'Medizin','slug'=>'medizin','active'=>true]);
