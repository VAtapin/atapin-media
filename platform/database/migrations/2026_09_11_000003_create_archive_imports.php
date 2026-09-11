<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_runs',function(Blueprint $t){
            $t->uuid('id')->primary();$t->string('source',32);$t->string('status',24)->default('queued')->index();
            $t->unsignedInteger('imported')->default(0);$t->unsignedInteger('skipped')->default(0);
            $t->json('notes')->nullable();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->timestamps();
        });
        Schema::create('source_records',function(Blueprint $t){
            $t->id();$t->string('source',32);$t->string('source_id');$t->string('kind',32)->index();
            $t->text('title');$t->longText('body')->nullable();$t->json('metadata');$t->string('status',24)->default('review')->index();
            $t->timestamps();$t->unique(['source','source_id']);
        });
        Schema::create('collections',function(Blueprint $t){
            $t->id();$t->string('title');$t->text('description')->nullable();$t->string('source',32);$t->string('source_id');
            $t->json('metadata')->nullable();$t->timestamps();$t->unique(['source','source_id']);
        });
        Schema::create('collection_items',function(Blueprint $t){
            $t->id();$t->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position');$t->string('source_id')->nullable();$t->text('title')->nullable();
            $t->string('availability')->nullable();$t->unique(['collection_id','position']);
        });
    }
    public function down():void{foreach(['collection_items','collections','source_records','import_runs'] as $table)Schema::dropIfExists($table);}
};
