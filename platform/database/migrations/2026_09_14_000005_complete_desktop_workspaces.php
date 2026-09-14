<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('source_records', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_title')->nullable();
        });
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16)->index();
            $table->string('name', 100);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('taxonomy_terms')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->string('refresh_status',16)->default('ready');
            $table->timestamps();
        });
        Schema::create('taxonomy_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_term_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->unique(['taxonomy_term_id', 'subject_type', 'subject_id'], 'taxonomy_subject_unique');
            $table->index(['subject_type', 'subject_id']);
        });
        Schema::create('publication_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('providers');
            $table->char('source_version',64)->nullable();
            $table->timestamp('publish_at')->index();
            $table->string('status', 24)->default('scheduled')->index();
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::create('desktop_ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 24);
            $table->text('question');
            $table->string('status', 24)->default('queued')->index();
            $table->text('answer')->nullable();
            $table->json('proposal')->nullable();
            $table->string('source_version', 64)->nullable();
            $table->timestamps();
        });
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 255);
            $table->text('body');
            $table->string('locale', 8)->nullable();
            $table->string('segment', 100)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('send_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::table('newsletter_subscriptions', function (Blueprint $table) {
            $table->json('tags')->nullable();
        });
        Schema::create('newsletter_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('queued')->index();
            $table->timestamp('sent_at')->nullable();
            $table->unique(['newsletter_campaign_id', 'newsletter_subscription_id'], 'newsletter_delivery_unique');
            $table->timestamps();
        });
        Schema::create('product_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->unique(['user_id', 'product_id']);
            $table->timestamps();
        });
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 32)->index();
            $table->string('subject', 255);
            $table->string('visitor_hash', 64);
            $table->date('occurred_on')->index();
            $table->unique(['event', 'subject', 'visitor_hash', 'occurred_on'], 'analytics_daily_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['analytics_events', 'product_entitlements', 'newsletter_deliveries', 'newsletter_campaigns', 'desktop_ai_requests', 'publication_schedules', 'taxonomy_assignments', 'taxonomy_terms'] as $table) Schema::dropIfExists($table);
        Schema::table('newsletter_subscriptions', fn (Blueprint $table) => $table->dropColumn('tags'));
        Schema::table('sales', function (Blueprint $table) { $table->dropConstrainedForeignId('user_id'); $table->dropColumn('product_title'); });
        Schema::table('products', function (Blueprint $table) { $table->dropConstrainedForeignId('project_id'); $table->dropColumn('metadata'); });
        Schema::table('source_records', fn (Blueprint $table) => $table->dropConstrainedForeignId('project_id'));
    }
};
