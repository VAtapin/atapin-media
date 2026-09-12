<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('avatar_path')->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('website', 1000)->nullable();
            $table->text('bio')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('user_profiles'); }
};
