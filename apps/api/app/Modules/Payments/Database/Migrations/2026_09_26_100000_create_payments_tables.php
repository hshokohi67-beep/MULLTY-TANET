<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_id');
            $table->string('method', 20);                  // online | cash | card_pos | other
            $table->string('gateway', 20)->nullable();     // zarinpal | fake (online only)
            $table->string('status', 20);                  // pending | paid | failed | expired
            $table->unsignedBigInteger('amount');          // rial
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->string('authority', 64)->nullable();   // gateway session id
            $table->string('ref_id', 64)->nullable();      // gateway reference after verify
            $table->string('card_pan', 32)->nullable();    // masked by the gateway
            $table->unsignedBigInteger('fee')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->string('reference', 100)->nullable();  // POS slip / manual reference
            $table->string('note', 300)->nullable();
            $table->ulid('recorded_by')->nullable();       // staff user for manual payments
            $table->string('failure_code', 20)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['gateway', 'authority']);
            $table->unique(['tenant_id', 'gateway', 'ref_id']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('orders');
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('payment_id');
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);                  // gateway_panel | cash | card | other
            $table->string('reference', 100)->nullable();
            $table->string('reason', 300);
            $table->string('idempotency_key', 100)->nullable();
            $table->ulid('actor_id')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['payment_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('payments');
        });

        // Append-only log of every gateway call. Credentials are stripped before insert.
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('payment_id');
            $table->string('action', 20);                  // request | verify | reconcile
            $table->boolean('success');
            $table->string('gateway_code', 20)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();

            $table->index(['payment_id', 'created_at']);
            $table->foreign(['payment_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('payments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payments');
    }
};
