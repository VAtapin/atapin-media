<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('record_classifications',function(Blueprint $table){
            $table->id();$table->foreignId('source_record_id')->constrained()->cascadeOnDelete();
            $table->string('provider',32);$table->string('model')->nullable();$table->string('status',32);
            $table->decimal('confidence',5,4)->nullable();$table->json('proposal')->nullable();$table->json('applied_changes')->nullable();$table->timestamps();
        });
    }
    public function down(): void {Schema::dropIfExists('record_classifications');}
};
