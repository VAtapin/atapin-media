<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_messages',function(Blueprint $table){
            $table->id();$table->string('name',120);$table->string('email');$table->string('subject',200);
            $table->text('body');$table->timestamp('consented_at');$table->timestamps();
        });
    }
    public function down(): void {Schema::dropIfExists('contact_messages');}
};
