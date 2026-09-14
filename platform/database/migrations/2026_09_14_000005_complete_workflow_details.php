<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('type', 32)->default('mixed');
            $table->date('start_date')->nullable();
            $table->string('next_action')->nullable();
            $table->json('tags')->nullable();
            $table->json('team_ids')->nullable();
            $table->uuid('cover_media_id')->nullable();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority', 16)->default('normal')->index();
            $table->foreignId('source_record_id')->nullable()->constrained('source_records')->nullOnDelete();
            $table->json('checklist')->nullable();
            $table->json('tags')->nullable();
        });
        Schema::table('desktop_ai_requests', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('context')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('desktop_ai_requests', function (Blueprint $table) {$table->dropConstrainedForeignId('product_id');$table->dropColumn('context');});
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_record_id');
            $table->dropColumn(['priority', 'checklist', 'tags']);
        });
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['type', 'start_date', 'next_action', 'tags', 'team_ids', 'cover_media_id']));
    }
};
