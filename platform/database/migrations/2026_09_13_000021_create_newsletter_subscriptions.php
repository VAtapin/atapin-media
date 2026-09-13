<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {Schema::create('newsletter_subscriptions',function(Blueprint $table){$table->id();$table->string('email')->unique();$table->string('locale',8);$table->string('token_hash',64);$table->string('status',24)->default('pending');$table->string('delivery_status',24)->default('pending');$table->timestamp('consented_at');$table->timestamp('confirmed_at')->nullable();$table->timestamps();});}
    public function down(): void {Schema::dropIfExists('newsletter_subscriptions');}
};
