<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{
        Schema::create('channel_message_runs',function(Blueprint $table){$table->id();$table->string('provider',32);$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$table->string('channel_id')->nullable();$table->string('status',32)->default('queued')->index();$table->unsignedInteger('pending_pages')->default(1);$table->unsignedInteger('pages')->default(0);$table->unsignedInteger('imported')->default(0);$table->string('error')->nullable();$table->timestamps();});
        Schema::create('channel_message_pages',function(Blueprint $table){$table->id();$table->foreignId('run_id')->constrained('channel_message_runs')->cascadeOnDelete();$table->string('page_key',64);$table->string('parent_id',200)->nullable();$table->string('video_id',32)->nullable();$table->text('page_token')->nullable();$table->string('status',32)->default('queued');$table->timestamps();$table->unique(['run_id','page_key']);});
    }
    public function down():void{Schema::dropIfExists('channel_message_pages');Schema::dropIfExists('channel_message_runs');}
};
