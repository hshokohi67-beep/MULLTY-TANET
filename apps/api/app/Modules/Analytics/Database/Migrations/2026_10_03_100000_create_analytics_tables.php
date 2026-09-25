<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (branch, business day): everything the reports sum over a range.
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->date('business_date');
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedBigInteger('sales')->default(0);       // totals of counted orders (rial)
            $table->unsignedBigInteger('discounts')->default(0);
            $table->unsignedBigInteger('refunds')->default(0);
            $table->unsignedInteger('cancelled')->default(0);      // cancelled + rejected orders
            $table->unsignedInteger('items')->default(0);          // units sold
            $table->unsignedInteger('buyers')->default(0);         // distinct known customers
            $table->json('channels');                              // {type: {orders, sales}}
            $table->json('payments');                              // {method: net amount}
            $table->unsignedBigInteger('cogs')->default(0);
            $table->unsignedInteger('item_lines')->default(0);
            $table->unsignedInteger('costed_lines')->default(0);
            $table->unsignedBigInteger('labour')->default(0);
            $table->unsignedBigInteger('expenses')->default(0);
            $table->unsignedBigInteger('waste')->default(0);
            $table->timestamp('computed_at');

            $table->unique(['tenant_id', 'branch_id', 'business_date']);
            $table->index(['tenant_id', 'business_date']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('hourly_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->date('business_date');
            $table->unsignedTinyInteger('hour'); // tenant-local 0–23
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedBigInteger('sales')->default(0);

            $table->unique(['tenant_id', 'branch_id', 'business_date', 'hour']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        Schema::create('product_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->date('business_date');
            $table->ulid('product_id')->nullable(); // null for lines whose product was deleted
            $table->string('product_name', 160);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedBigInteger('revenue')->default(0);
            $table->unsignedBigInteger('cost')->default(0);
            $table->unsignedInteger('costed_quantity')->default(0);

            $table->index(['tenant_id', 'business_date']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnDelete();
        });

        // Days whose source data changed since they were last rolled up.
        Schema::create('metric_dirty_days', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->date('business_date');
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'business_date']);
        });

        // Existing history: mark every day that has orders or expenses so the scheduler builds it.
        $days = DB::table('orders')->select('tenant_id', 'business_date')->distinct()
            ->union(DB::table('expenses')->select('tenant_id', DB::raw('spent_on as business_date'))->distinct())
            ->get();
        foreach ($days->chunk(500) as $chunk) {
            DB::table('metric_dirty_days')->insertOrIgnore($chunk->map(fn ($d) => [
                'id' => (string) Str::ulid(),
                'tenant_id' => $d->tenant_id,
                'business_date' => substr((string) $d->business_date, 0, 10),
                'created_at' => now(),
            ])->values()->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_dirty_days');
        Schema::dropIfExists('product_metrics');
        Schema::dropIfExists('hourly_metrics');
        Schema::dropIfExists('daily_metrics');
    }
};
