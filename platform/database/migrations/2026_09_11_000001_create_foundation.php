<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete(); $t->primary(['permission_id', 'role_id']);
        });
        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->primary(['role_id', 'user_id']);
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary(); $t->json('value'); $t->timestamps();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('title'); $t->string('original_name');
            $t->string('kind', 24)->index(); $t->string('mime'); $t->unsignedBigInteger('bytes');
            $t->string('disk', 32); $t->text('path'); $t->char('sha256', 64)->nullable()->index();
            $t->string('status', 24)->default('ready')->index();
            $t->string('source', 32)->default('upload'); $t->string('source_id')->nullable();
            $t->json('metadata')->nullable(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps(); $t->unique(['source', 'source_id']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action')->index(); $t->string('subject')->nullable();
            $t->json('context')->nullable(); $t->timestamp('created_at')->index();
        });
    }
    public function down(): void
    {
        foreach (['audit_events','media','settings','role_user','permission_role','permissions','roles'] as $table) Schema::dropIfExists($table);
    }
};
