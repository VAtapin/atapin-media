<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('taxonomy_terms', function (Blueprint $table) {
            $table->uuid('cover_media_id')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('taxonomy_terms', fn (Blueprint $table) => $table->dropColumn('cover_media_id'));
    }
};
