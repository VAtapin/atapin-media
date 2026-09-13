<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('products',function(Blueprint $table){$table->string('author')->nullable();$table->text('contents')->nullable();$table->string('isbn',32)->nullable();$table->string('language',8)->nullable();$table->unsignedInteger('page_count')->nullable();});
        Schema::create('book_reviews',function(Blueprint $table){$table->id();$table->foreignId('product_id')->constrained()->cascadeOnDelete();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->unsignedTinyInteger('rating');$table->text('body');$table->string('status',24)->default('pending')->index();$table->timestamps();$table->unique(['product_id','user_id']);});
    }
    public function down(): void {Schema::dropIfExists('book_reviews');Schema::table('products',fn(Blueprint $table)=>$table->dropColumn(['author','contents','isbn','language','page_count']));}
};
