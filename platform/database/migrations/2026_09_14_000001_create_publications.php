<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_record_id')->constrained('source_records')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('direction', 16)->default('outbound');
            $table->string('status', 24)->default('queued')->index();
            $table->string('external_id')->nullable();
            $table->text('external_url')->nullable();
            $table->string('remote_status', 32)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['source_record_id', 'provider', 'direction']);
            $table->index(['provider', 'status']);
        });

        Schema::create('external_items', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('external_id', 255);
            $table->foreignId('source_record_id')->nullable()->constrained('source_records')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('status', 24)->default('discovered')->index();
            $table->timestamp('remote_published_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_items');
        Schema::dropIfExists('publications');
    }
};
