<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resumable_media_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_key', 36)->unique();
            $table->string('original_name');
            $table->unsignedBigInteger('bytes');
            $table->unsignedBigInteger('offset')->default(0);
            $table->string('status', 24)->default('uploading')->index();
            $table->string('disk', 32);
            $table->text('staging_path');
            $table->text('final_path')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
        });

        Schema::create('resumable_media_upload_chunks', function (Blueprint $table): void {
            $table->uuid('upload_id');
            $table->unsignedBigInteger('offset');
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->primary(['upload_id', 'offset']);
            $table->foreign('upload_id')->references('id')->on('resumable_media_uploads')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumable_media_upload_chunks');
        Schema::dropIfExists('resumable_media_uploads');
    }
};
