<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('public_live_presence',function(Blueprint $table){
            $table->id();$table->foreignId('record_id')->constrained('source_records')->cascadeOnDelete();
            $table->char('session_hash',64);$table->timestamp('seen_at');
            $table->unique(['record_id','session_hash']);$table->index(['record_id','seen_at']);
        });
    }
    public function down(): void {Schema::dropIfExists('public_live_presence');}
};
