<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('parent_id')->nullable();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'parent_id', 'sort']);
            $table->foreign(['parent_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('categories');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 160);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->text('search_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->json('nutrition')->nullable();
            $table->json('dietary_tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'is_active', 'sort']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->ulid('tenant_id');
            $table->ulid('product_id');
            $table->ulid('category_id');
            $table->unsignedSmallInteger('sort')->default(0);

            $table->primary(['product_id', 'category_id']);
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['category_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('categories')->cascadeOnDelete();
            $table->index(['category_id', 'sort']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('product_id');
            $table->string('name', 80)->nullable(); // null = the product's single default variant
            $table->string('sku', 64)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'sku']);
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'sort']);
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('variant_id');
            $table->ulid('branch_id')->nullable(); // null = base price for every branch
            // NULLs never collide in unique indexes, so uniqueness is enforced on this generated key.
            $table->string('branch_scope', 26)->storedAs("coalesce(branch_id, '')");
            $table->unsignedBigInteger('amount'); // rial
            $table->timestamps();

            $table->unique(['variant_id', 'branch_scope']);
            $table->foreign(['variant_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('price_change_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('variant_id');
            $table->ulid('branch_id')->nullable();
            $table->unsignedBigInteger('old_amount')->nullable();
            $table->unsignedBigInteger('new_amount')->nullable(); // null = branch override removed
            $table->string('reason', 20); // manual | bulk | import
            $table->ulid('batch_id')->nullable();
            $table->string('actor_id', 26)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['variant_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('product_variants')->cascadeOnDelete();
            $table->index(['tenant_id', 'variant_id', 'created_at']);
            $table->index(['tenant_id', 'batch_id']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('product_id');
            $table->string('path');
            $table->string('alt', 160)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'sort']);
        });

        Schema::create('product_availability', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('product_id');
            $table->ulid('branch_id');
            $table->string('status', 20); // available | sold_out | hidden
            $table->timestamp('sold_out_until')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']);
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 100);
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(0); // 0 = unlimited
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('modifier_group_id');
            $table->string('name', 100);
            $table->bigInteger('price_delta')->default(0); // rial; may be 0
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->foreign(['modifier_group_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('modifier_groups')->cascadeOnDelete();
        });

        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->ulid('tenant_id');
            $table->ulid('product_id');
            $table->ulid('modifier_group_id');
            $table->unsignedSmallInteger('sort')->default(0);

            $table->primary(['product_id', 'modifier_group_id']);
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['modifier_group_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('modifier_groups')->cascadeOnDelete();
        });

        Schema::create('import_mappings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('source', 40);      // e.g. woocommerce
            $table->string('source_type', 40); // category | product | variation | upsell_group
            $table->string('source_id', 64);
            $table->string('target_id', 26);
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        foreach (['import_mappings', 'product_modifier_groups', 'modifiers', 'modifier_groups', 'product_availability', 'product_images', 'price_change_logs', 'product_prices', 'product_variants', 'product_categories', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
