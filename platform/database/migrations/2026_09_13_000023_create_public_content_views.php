<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('public_content_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('source_records')->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->date('viewed_on');
            $table->timestamps();
            $table->unique(['record_id', 'visitor_hash', 'viewed_on']);
            $table->index(['record_id', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_content_views');
    }
};
