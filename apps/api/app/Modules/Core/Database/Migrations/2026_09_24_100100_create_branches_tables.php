<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 120);
            $table->string('slug', 64);
            $table->string('phone', 20)->nullable();
            $table->string('province', 60)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            // Lets child tables reference (id, tenant_id) so a row can never point at another tenant's branch.
            $table->unique(['id', 'tenant_id']);
        });

        Schema::create('branch_opening_hours', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->unsignedTinyInteger('weekday'); // ISO 1 = Monday … 7 = Sunday
            $table->time('opens_at');
            $table->time('closes_at'); // closes_at <= opens_at means the interval ends the next day
            $table->timestamps();

            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
            $table->index(['branch_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_opening_hours');
        Schema::dropIfExists('branches');
    }
};
