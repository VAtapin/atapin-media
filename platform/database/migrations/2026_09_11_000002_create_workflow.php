<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function(Blueprint $t) {
            $t->id(); $t->string('title'); $t->text('description')->nullable();
            $t->string('status',24)->default('idea')->index(); $t->date('due_date')->nullable()->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $t->timestamps();
        });
        Schema::create('tasks', function(Blueprint $t) {
            $t->id(); $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title'); $t->text('description')->nullable(); $t->string('status',24)->default('open')->index();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->date('due_date')->nullable()->index(); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tasks'); Schema::dropIfExists('projects'); }
};
