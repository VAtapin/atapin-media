<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up():void{Schema::table('newsletter_subscriptions',function(Blueprint $table){$table->timestamp('consented_at')->nullable()->change();$table->json('import_provenance')->nullable();});}
    public function down():void{if(DB::table('newsletter_subscriptions')->whereNull('consented_at')->exists())throw new RuntimeException('Cannot invent consent to roll back imported contacts.');Schema::table('newsletter_subscriptions',function(Blueprint $table){$table->timestamp('consented_at')->nullable(false)->change();$table->dropColumn('import_provenance');});}
};
