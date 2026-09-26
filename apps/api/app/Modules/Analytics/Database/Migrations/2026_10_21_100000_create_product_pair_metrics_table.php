<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Bought together": per business day and branch, how many orders held both products
        // (stored once per pair, product_a < product_b). Rebuilt by RollupDay like every aggregate.
        Schema::create('product_pair_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->date('business_date');
            $table->ulid('product_a');
            $table->ulid('product_b');
            $table->unsignedInteger('orders')->default(0);

            $table->index(['tenant_id', 'business_date']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pair_metrics');
    }
};
