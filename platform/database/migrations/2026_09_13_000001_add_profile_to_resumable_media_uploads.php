<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resumable_media_uploads', function (Blueprint $table): void {
            $table->string('profile', 32)->default('media_library')->after('request_key');
        });
    }

    public function down(): void
    {
        Schema::table('resumable_media_uploads', function (Blueprint $table): void {
            $table->dropColumn('profile');
        });
    }
};
