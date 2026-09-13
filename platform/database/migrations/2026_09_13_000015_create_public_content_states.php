<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('public_content_states',function(Blueprint $table){
            $table->id();$table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type',16);$table->unsignedBigInteger('subject_id');$table->string('action',24);
            $table->json('value');$table->timestamps();
            $table->unique(['user_id','subject_type','subject_id','action'],'public_content_state_unique');
            $table->index(['subject_type','subject_id','action'],'public_content_state_subject');
        });
    }
    public function down(): void {Schema::dropIfExists('public_content_states');}
};
