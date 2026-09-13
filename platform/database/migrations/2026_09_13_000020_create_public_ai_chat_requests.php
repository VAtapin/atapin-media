<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('public_ai_budgets',function(Blueprint $table){$table->date('day')->primary();$table->unsignedInteger('used')->default(0);});
        Schema::create('public_ai_chat_requests',function(Blueprint $table){
            $table->id();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->foreignId('record_id')->constrained('source_records')->cascadeOnDelete();
            $table->text('question');$table->text('answer')->nullable();$table->string('status',30)->default('queued');$table->timestamps();
        });
    }
    public function down(): void {Schema::dropIfExists('public_ai_chat_requests');Schema::dropIfExists('public_ai_budgets');}
};
