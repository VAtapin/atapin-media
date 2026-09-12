<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('asset_role', 32)->nullable()->index()->after('kind');
            $table->uuid('parent_id')->nullable()->index()->after('path');
            $table->timestamp('captured_at')->nullable()->index()->after('metadata');
            $table->string('classification_origin', 24)->nullable()->after('status');
            $table->decimal('classification_confidence', 5, 4)->nullable()->after('classification_origin');
            $table->string('classification_version', 64)->nullable()->after('classification_confidence');
            $table->timestamp('classified_at')->nullable()->after('classification_version');
            $table->timestamp('archived_at')->nullable()->index()->after('classified_at');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('media_tag', function (Blueprint $table) {
            $table->uuid('media_id');
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['media_id', 'tag_id']);
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });
        Schema::create('collection_media', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->uuid('media_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->primary(['collection_id', 'media_id']);
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });
        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->uuid('media_id');
            $table->string('used_as', 32);
            $table->string('subject_type', 128);
            $table->string('subject_id', 64);
            $table->timestamps();
            $table->unique(['media_id', 'used_as', 'subject_type', 'subject_id'], 'media_usage_unique');
            $table->index(['subject_type', 'subject_id']);
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });
        Schema::create('media_classifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('media_id');
            $table->string('provider', 64);
            $table->string('model', 128)->nullable();
            $table->string('status', 24)->default('queued')->index();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('proposal')->nullable();
            $table->json('applied_changes')->nullable();
            $table->timestamps();
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_classifications');
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('collection_media');
        Schema::dropIfExists('media_tag');
        Schema::dropIfExists('tags');
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['asset_role', 'parent_id', 'captured_at', 'classification_origin', 'classification_confidence', 'classification_version', 'classified_at', 'archived_at']);
        });
    }
};
