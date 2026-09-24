<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal tenant-scoped customer identity, needed for OTP login (Phase 1).
     * Profile, addresses, preferences and devices are added in Phase 5.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('phone_e164', 16);
            $table->string('name', 120)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            // Same phone at two tenants = two different customers (master prompt §5).
            $table->unique(['tenant_id', 'phone_e164']);
            $table->unique(['id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
