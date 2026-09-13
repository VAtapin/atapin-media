<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {Schema::table('contact_messages',function(Blueprint $table){
        $table->string('delivery_status',30)->default('pending');
        $table->timestamp('delivered_at')->nullable();
    });}
    public function down(): void {Schema::table('contact_messages',fn(Blueprint $table)=>$table->dropColumn(['delivery_status','delivered_at']));}
};
