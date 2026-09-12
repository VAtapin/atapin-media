<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->string('source_kind', 64)->nullable()->after('source')->index();
            $table->json('source_options')->nullable()->after('status');
            $table->string('target_profile', 64)->nullable()->after('source_options');
            $table->unsignedInteger('discovered')->default(0)->after('skipped');
            $table->json('progress')->nullable()->after('notes');
            $table->longText('error')->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('updated_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'source_kind',
                'source_options',
                'target_profile',
                'discovered',
                'progress',
                'error',
                'started_at',
                'finished_at',
            ]);
        });
    }
};
