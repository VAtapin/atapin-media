<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('live_browser_sessions',function(Blueprint $table){
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_record_id')->constrained()->cascadeOnDelete();
            $table->string('status',24)->default('starting')->index();
            $table->text('upstream_location')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('live_browser_sessions'); }
};
