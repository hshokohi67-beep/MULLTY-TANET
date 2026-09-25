<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quantities are in the ingredient's base unit (g, ml or pcs); costs are integer rial per
        // 1000 base units (per kg / per litre / per 1000 pieces), so per-gram costs stay exact.
        Schema::create('ingredients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 120);
            $table->string('unit', 4); // g | ml | pcs
            $table->string('pack_label', 60)->nullable();
            $table->decimal('pack_size', 14, 3)->nullable();
            $table->unsignedBigInteger('avg_cost')->default(0);
            $table->decimal('low_stock_threshold', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('ingredient_stocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('ingredient_id');
            $table->ulid('branch_id');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['ingredient_id', 'branch_id']);
            $table->foreign(['ingredient_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('ingredients')->cascadeOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('ingredient_id');
            $table->ulid('branch_id');
            $table->string('type', 16);
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->unsignedBigInteger('unit_cost')->nullable(); // rial per 1000 base units
            $table->ulid('order_id')->nullable();
            $table->ulid('order_item_id')->nullable();
            $table->ulid('purchase_order_id')->nullable();
            $table->string('note', 300)->nullable();
            $table->string('actor_type', 16)->default('system');
            $table->ulid('actor_id')->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->timestamp('created_at');

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'ingredient_id', 'created_at']);
            $table->index(['tenant_id', 'order_id']);
            $table->foreign(['ingredient_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('ingredients')->cascadeOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('variant_id');
            $table->ulid('ingredient_id');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();

            $table->unique(['variant_id', 'ingredient_id']);
            $table->foreign(['variant_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['ingredient_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('ingredients')->cascadeOnDelete();
        });

        // Modifier effects may be negative: «شیر بادام» = +200 ml almond milk, −200 ml milk.
        Schema::create('modifier_recipe_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('modifier_id');
            $table->ulid('ingredient_id');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();

            $table->unique(['modifier_id', 'ingredient_id']);
            $table->foreign(['modifier_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('modifiers')->cascadeOnDelete();
            $table->foreign(['ingredient_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('ingredients')->cascadeOnDelete();
        });

        // What each sold line cost at the moment of sale (kept even if the order is cancelled).
        Schema::create('order_item_costs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_id');
            $table->ulid('order_item_id');
            $table->unsignedBigInteger('cost');
            $table->json('breakdown');
            $table->timestamps();

            $table->unique('order_item_id');
            $table->index(['tenant_id', 'order_id']);
            $table->foreign(['order_item_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('order_items')->cascadeOnDelete();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('supplier_id');
            $table->ulid('branch_id');
            $table->unsignedInteger('number');
            $table->string('status', 12)->default('draft');
            $table->date('expected_on')->nullable();
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->foreign(['supplier_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('suppliers');
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('purchase_order_id');
            $table->ulid('ingredient_id');
            $table->decimal('quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->unsignedBigInteger('unit_price'); // rial per 1000 base units
            $table->unsignedBigInteger('line_total');
            $table->timestamps();

            $table->foreign(['purchase_order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('purchase_orders')->cascadeOnDelete();
            $table->foreign(['ingredient_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('ingredients');
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('purchase_order_id');
            $table->unsignedBigInteger('amount');
            $table->string('method', 12);
            $table->string('note', 300)->nullable();
            $table->timestamp('paid_at');
            $table->ulid('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign(['purchase_order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('purchase_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['supplier_payments', 'purchase_order_items', 'purchase_orders', 'suppliers', 'order_item_costs', 'modifier_recipe_items', 'recipe_items', 'stock_movements', 'ingredient_stocks', 'ingredients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
