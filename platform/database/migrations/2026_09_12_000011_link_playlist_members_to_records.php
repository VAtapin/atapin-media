<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {Schema::table('collection_items',fn(Blueprint $table)=>$table->foreignId('source_record_id')->nullable()->constrained()->nullOnDelete());}
    public function down(): void {Schema::table('collection_items',fn(Blueprint $table)=>$table->dropConstrainedForeignId('source_record_id'));}
};
