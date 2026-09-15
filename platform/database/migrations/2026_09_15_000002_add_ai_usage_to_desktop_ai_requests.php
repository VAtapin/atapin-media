<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('desktop_ai_requests', function (Blueprint $table) {
            $table->string('model', 120)->nullable()->after('context');
            $table->unsignedInteger('input_tokens')->nullable()->after('model');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            $table->unsignedInteger('total_tokens')->nullable()->after('output_tokens');
            $table->unsignedBigInteger('estimated_cost_micros')->nullable()->after('total_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('desktop_ai_requests', function (Blueprint $table) {
            $table->dropColumn(['model', 'input_tokens', 'output_tokens', 'total_tokens', 'estimated_cost_micros']);
        });
    }
};
