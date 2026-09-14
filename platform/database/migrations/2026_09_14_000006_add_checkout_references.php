<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
    public function up(): void { \Illuminate\Support\Facades\Schema::table('sales',function(Blueprint $t){$t->text('checkout_url')->nullable();$t->string('provider_payment_id')->nullable()->index();}); }
    public function down(): void { \Illuminate\Support\Facades\Schema::table('sales',function(Blueprint $t){$t->dropIndex(['provider_payment_id']);$t->dropColumn(['checkout_url','provider_payment_id']);}); }
};
