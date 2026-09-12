<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', fn (Blueprint $table) => $table->index(['sha256','bytes'],'media_checksum_lookup'));
        Schema::create('media_originals', function (Blueprint $table) {
            $table->id(); $table->uuid('media_id');
            $table->string('disk',64); $table->text('path'); $table->string('original_name',255);
            $table->string('source',32); $table->string('source_id',255); $table->string('sha256',64)->index();
            $table->unsignedBigInteger('bytes'); $table->string('identity',64)->unique(); $table->timestamps();
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });
        Schema::create('source_record_snapshots', function (Blueprint $table) {
            $table->id(); $table->foreignId('source_record_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint',64); $table->string('kind',32); $table->text('title');
            $table->longText('body')->nullable(); $table->json('metadata'); $table->timestamps();
            $table->unique(['source_record_id','fingerprint']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('source_record_snapshots'); Schema::dropIfExists('media_originals');
        Schema::table('media', fn (Blueprint $table) => $table->dropIndex('media_checksum_lookup'));
    }
};
