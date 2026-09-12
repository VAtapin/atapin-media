<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->string('title'); $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->nullable(); $table->string('currency', 3)->default('EUR');
            $table->string('status', 24)->default('draft')->index(); $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id(); $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_email')->nullable()->index(); $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('EUR'); $table->string('status', 24)->default('pending')->index();
            $table->string('provider', 64)->nullable(); $table->string('provider_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sales'); Schema::dropIfExists('products'); }
};