<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 120);
            $table->string('code', 40)->nullable();          // stored upper-case; null = automatic
            $table->string('kind', 20);                      // percent | fixed
            $table->unsignedBigInteger('value');             // basis points (percent) or rial (fixed)
            $table->string('applies_to', 20)->default('order'); // order | items
            $table->unsignedBigInteger('min_order')->default(0);
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('schedule')->nullable();            // {weekdays: [..ISO..], from: "HH:MM", to: "HH:MM"}
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('priority')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('discount_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('discount_id');
            $table->string('rule_type', 20); // product | category | branch | order_type | customer
            $table->string('target', 26);    // id, or order-type key
            $table->timestamps();

            $table->unique(['discount_id', 'rule_type', 'target']);
            $table->foreign(['discount_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('discounts')->cascadeOnDelete();
        });

        Schema::create('discount_usages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('discount_id');
            $table->ulid('order_id');
            $table->ulid('customer_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['discount_id', 'order_id']);
            $table->index(['discount_id', 'customer_id']);
            $table->foreign(['discount_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('discounts');
            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_usages');
        Schema::dropIfExists('discount_rules');
        Schema::dropIfExists('discounts');
    }
};
