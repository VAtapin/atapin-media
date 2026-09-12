<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {Schema::create('import_items',function(Blueprint $table){
        $table->id();$table->uuid('import_run_id');$table->foreign('import_run_id')->references('id')->on('import_runs')->cascadeOnDelete();
        $table->string('key',64);$table->text('label');$table->string('type',32);$table->string('outcome',32)->index();
        $table->string('subject_id',64)->nullable();$table->json('metadata')->nullable();$table->timestamps();$table->unique(['import_run_id','key']);
    });}
    public function down(): void {Schema::dropIfExists('import_items');}
};
