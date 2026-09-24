<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 60);
            $table->unsignedBigInteger('min_spend');                       // rial, lifetime
            $table->string('color', 7)->default('#94A3B8');
            $table->unsignedInteger('points_multiplier')->default(10_000); // basis points, 10000 = x1
            $table->string('perks', 300)->nullable();
            $table->smallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'min_spend']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('customer_id');
            $table->bigInteger('balance')->default(0); // rial; negative only after a reward clawback
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'customer_id']);
            $table->foreign(['customer_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('wallet_id');
            $table->string('type', 30);
            $table->bigInteger('amount');          // signed rial
            $table->bigInteger('balance_after');
            $table->ulid('order_id')->nullable();
            $table->ulid('payment_id')->nullable();
            $table->string('description', 300)->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->ulid('actor_id')->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['wallet_id', 'created_at']);
            $table->index(['tenant_id', 'order_id']);
            $table->foreign(['wallet_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('wallets');
        });

        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('customer_id');
            $table->bigInteger('points')->default(0);
            $table->bigInteger('lifetime_spend')->default(0); // rial of completed, net-paid orders
            $table->ulid('tier_id')->nullable();
            $table->timestamp('tier_since')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'tier_id']);
            $table->foreign(['customer_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers');
            $table->foreign(['tier_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('loyalty_tiers');
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('account_id');
            $table->string('type', 30);
            $table->bigInteger('points');          // signed
            $table->bigInteger('balance_after');
            $table->ulid('order_id')->nullable();
            $table->string('description', 300)->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->ulid('actor_id')->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['account_id', 'created_at']);
            $table->foreign(['account_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('loyalty_accounts');
        });

        Schema::create('cashback_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 100);
            $table->ulid('category_id')->nullable();        // null = the whole order
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->string('kind', 10);                      // fixed | percent
            $table->unsignedBigInteger('value');             // rial or basis points
            $table->unsignedBigInteger('max_reward')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            // No FK on category_id: a composite FK can't "set null" without nulling tenant_id, and a rule
            // must not block deleting a category. The id is validated on write; a rule whose category
            // is gone simply stops matching.
            $table->index(['tenant_id', 'is_active']);
        });

        // What a completed order earned. Refunds rescale the targets; *_posted track what the ledgers hold.
        Schema::create('loyalty_order_awards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_id');
            $table->ulid('customer_id');
            $table->unsignedBigInteger('base_amount');
            $table->unsignedBigInteger('points_full');
            $table->unsignedBigInteger('cashback_full');
            $table->bigInteger('points_posted')->default(0);
            $table->bigInteger('cashback_posted')->default(0);
            $table->bigInteger('spend_posted')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_id']);
            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('orders');
            $table->foreign(['customer_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_order_awards');
        Schema::dropIfExists('cashback_rules');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('loyalty_tiers');
    }
};
