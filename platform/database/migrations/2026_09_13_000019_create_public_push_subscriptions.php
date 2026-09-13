<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {Schema::create('public_push_subscriptions',function(Blueprint $table){
        $table->id();$table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('record_id')->constrained('source_records')->cascadeOnDelete();
        $table->char('endpoint_hash',64);$table->text('subscription');
        $table->unsignedBigInteger('sent_for')->nullable();$table->unsignedBigInteger('failed_for')->nullable();
        $table->timestamps();$table->unique(['user_id','record_id','endpoint_hash'],'public_push_unique');
    });}
    public function down(): void {Schema::dropIfExists('public_push_subscriptions');}
};
