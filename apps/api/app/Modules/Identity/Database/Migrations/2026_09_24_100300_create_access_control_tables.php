<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->foreignUlid('user_id')->constrained();
            $table->string('status', 20)->default('active'); // active | disabled
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['id', 'tenant_id']);
        });

        // Global catalogue, synced from code (App\Modules\Identity\Support\PermissionCatalog).
        Schema::create('permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('group', 60);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('key', 60);
            $table->string('name', 100);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->unique(['id', 'tenant_id']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUlid('role_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('permission_id')->constrained()->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('tenant_user_roles', function (Blueprint $table) {
            $table->ulid('tenant_id');
            $table->ulid('tenant_user_id');
            $table->ulid('role_id');

            $table->primary(['tenant_user_id', 'role_id']);
            // Both sides must belong to the same tenant.
            $table->foreign(['tenant_user_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('tenant_users')->cascadeOnDelete();
            $table->foreign(['role_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('tenant_users');
    }
};
