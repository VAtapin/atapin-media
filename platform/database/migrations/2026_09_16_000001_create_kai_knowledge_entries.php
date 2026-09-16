<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kai_knowledge_entries', function (Blueprint $table): void {
            $table->string('key', 96)->primary();
            $table->string('title');
            $table->string('status', 24);
            $table->json('keywords');
            $table->text('content');
            $table->string('sources', 1000);
            $table->char('source_hash', 64);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('kai_knowledge_entries'); }
};
