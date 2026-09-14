<?php
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'||getenv('DB_CONNECTION')!=='sqlite'||!str_contains((string)getenv('DB_DATABASE'),'desktop-workspaces'))exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app(\App\Services\Access::class)->seed();
$user=\App\Models\User::firstOrCreate(['email'=>'workspace@example.test'],['name'=>'Workspace Test','password'=>'test-only-password']);$user->roles()->syncWithoutDetaching(\App\Models\Role::where('name','Owner')->firstOrFail());
$parent=\App\Models\SourceRecord::firstOrCreate(['source'=>'website','source_id'=>'workspace-browser-parent'],['kind'=>'post','title'=>'Browser inbox article','body'=>'Public testing article','status'=>'ready','metadata'=>['public_section'=>'beitraege','public_published'=>true]]);
\App\Models\SourceRecord::firstOrCreate(['source'=>'website','source_id'=>'workspace-browser-comment'],['kind'=>'comment','title'=>'Browser inbox comment','body'=>'Testing comment','status'=>'ready','metadata'=>['website_comment'=>true,'parent_record_id'=>$parent->id,'public_published'=>true,'moderation'=>['state'=>'published']]]);
