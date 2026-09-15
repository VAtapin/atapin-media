<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence', 16)->default('once')->index();
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->string('recurrence_unit', 8)->nullable();
            $table->date('recurrence_until')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn([
            'recurrence', 'recurrence_interval', 'recurrence_unit', 'recurrence_until',
        ]));
    }
};
