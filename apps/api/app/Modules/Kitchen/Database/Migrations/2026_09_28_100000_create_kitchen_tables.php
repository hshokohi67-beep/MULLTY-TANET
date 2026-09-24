<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_stations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->string('name', 60);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('late_after_minutes')->default(7);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'branch_id']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
        });

        // Which station prepares a product in a branch. Unmapped products go to the branch's default station.
        Schema::create('kitchen_station_products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->ulid('station_id');
            $table->ulid('product_id');
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->foreign(['station_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('kitchen_stations')->cascadeOnDelete();
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('kitchen_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_id');
            $table->ulid('order_item_id');
            $table->ulid('station_id');
            $table->string('status', 20);               // queued | preparing | ready | cancelled
            $table->unsignedInteger('quantity');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique('order_item_id');
            $table->index(['tenant_id', 'station_id', 'status']);
            $table->index(['order_id']);
            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['order_item_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('order_items')->cascadeOnDelete();
            $table->foreign(['station_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('kitchen_stations');
        });

        // Append-only: who did what in the kitchen (the legacy KDS had no attribution at all).
        Schema::create('kitchen_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('order_id');
            $table->ulid('kitchen_item_id')->nullable();
            $table->ulid('station_id')->nullable();
            $table->string('type', 30);
            $table->string('actor_type', 20);            // user | device | system
            $table->ulid('actor_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'order_id']);
            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('orders')->cascadeOnDelete();
        });

        Schema::create('kitchen_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->ulid('station_id')->nullable();      // null = every station of the branch
            $table->string('name', 60);
            $table->string('pairing_code_hash', 64)->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'pairing_code_hash']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
            $table->foreign(['station_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('kitchen_stations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_devices');
        Schema::dropIfExists('kitchen_events');
        Schema::dropIfExists('kitchen_items');
        Schema::dropIfExists('kitchen_station_products');
        Schema::dropIfExists('kitchen_stations');
    }
};
