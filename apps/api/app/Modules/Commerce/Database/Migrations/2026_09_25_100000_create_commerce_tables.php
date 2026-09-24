<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->string('label', 40); // «میز ۱۲» ("table 12"), «تراس ۳» ("terrace 3")
            $table->unsignedTinyInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'label']);
            $table->unique(['id', 'tenant_id']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
        });

        Schema::create('table_qr_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('table_id');
            $table->char('token_hash', 64)->unique();
            $table->string('token_hint', 8);
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign(['table_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('restaurant_tables')->cascadeOnDelete();
        });

        Schema::create('order_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->ulid('table_id');
            $table->string('status', 20)->default('open'); // open | closed
            $table->timestamp('opened_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['table_id', 'status']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
            $table->foreign(['table_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('restaurant_tables');
        });

        Schema::create('table_session_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_session_id');
            $table->ulid('table_id');
            $table->string('type', 20); // call_waiter | request_bill
            $table->string('status', 20)->default('open'); // open | acknowledged
            $table->string('acknowledged_by', 26)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->foreign(['order_session_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('order_sessions')->cascadeOnDelete();
            $table->foreign(['table_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('restaurant_tables')->cascadeOnDelete();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->string('name', 80);
            $table->string('type', 20)->default('radius'); // radius | polygon (future)
            $table->unsignedInteger('radius_m')->nullable();
            $table->json('polygon')->nullable();
            $table->unsignedBigInteger('delivery_fee')->default(0);        // rial
            $table->unsignedBigInteger('free_delivery_min')->nullable();   // rial
            $table->unsignedBigInteger('min_order')->default(0);           // rial
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->ulid('customer_id')->nullable();
            $table->ulid('order_session_id')->nullable();
            $table->char('cart_token_hash', 64)->unique();
            $table->string('order_type', 20);
            $table->string('status', 20)->default('active'); // active | converted | abandoned
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
            $table->foreign(['customer_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers');
            $table->foreign(['order_session_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('order_sessions');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('cart_id');
            $table->ulid('product_id');
            $table->ulid('variant_id');
            $table->unsignedSmallInteger('quantity');
            $table->json('modifier_ids')->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->foreign(['cart_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('carts')->cascadeOnDelete();
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->cascadeOnDelete();
            $table->foreign(['variant_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('product_variants')->cascadeOnDelete();
        });

        Schema::create('order_counters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->date('date'); // tenant-local business date
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'date']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->date('business_date');
            $table->unsignedInteger('daily_number');
            $table->ulid('customer_id')->nullable();
            $table->ulid('table_id')->nullable();
            $table->ulid('order_session_id')->nullable();
            $table->string('type', 20);     // dine_in | takeaway | delivery | qr_table | counter | online | phone
            $table->string('source', 20);   // web | qr | dashboard | marketplace | phone
            $table->string('status', 20);
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('payment_method_intent', 20)->nullable(); // cash | online | card | wallet (Phase 4 decides)
            $table->timestamp('scheduled_for')->nullable();
            $table->string('customer_note', 300)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone_e164', 16)->nullable();
            $table->json('address_snapshot')->nullable();
            $table->ulid('delivery_zone_id')->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('total');
            $table->json('discount_snapshot')->nullable();
            $table->string('idempotency_key', 80)->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['branch_id', 'business_date', 'daily_number']);
            $table->index(['tenant_id', 'branch_id', 'status', 'placed_at']);
            $table->index(['tenant_id', 'customer_id', 'placed_at']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
            $table->foreign(['customer_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers');
            $table->foreign(['table_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('restaurant_tables');
            $table->foreign(['order_session_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('order_sessions');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_id');
            // No FK to products: the order must survive any later menu change (soft or hard delete).
            $table->ulid('product_id')->nullable();
            $table->ulid('variant_id')->nullable();
            $table->string('product_name', 160);
            $table->string('variant_name', 80)->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->bigInteger('modifiers_total')->default(0);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('orders')->cascadeOnDelete();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_item_id');
            $table->ulid('modifier_id')->nullable();
            $table->string('group_name', 100);
            $table->string('name', 100);
            $table->bigInteger('price_delta');
            $table->timestamps();

            $table->foreign(['order_item_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('order_items')->cascadeOnDelete();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('actor_type', 20)->nullable();
            $table->string('actor_id', 26)->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('orders')->cascadeOnDelete();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['order_status_history', 'order_item_modifiers', 'order_items', 'orders', 'order_counters', 'cart_items', 'carts', 'delivery_zones', 'table_session_requests', 'order_sessions', 'table_qr_codes', 'restaurant_tables'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
