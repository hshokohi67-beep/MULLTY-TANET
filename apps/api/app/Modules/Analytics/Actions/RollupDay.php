<?php

namespace App\Modules\Analytics\Actions;

use App\Modules\Analytics\Models\DailyMetric;
use App\Modules\Analytics\Models\HourlyMetric;
use App\Modules\Analytics\Models\MetricDirtyDay;
use App\Modules\Analytics\Models\ProductMetric;
use App\Modules\Analytics\Support\SalesRules;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Core\Models\Branch;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\OrderItemCost;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Operations\Models\Expense;
use App\Modules\Operations\Support\Payroll;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rebuilds one business day's aggregates for every branch of the current tenant. Idempotent: the
 * day's rows are deleted and recomputed in one transaction, so late data (a cancellation, a refund,
 * a corrected clock-out) is picked up by simply rolling the day up again. The dirty mark is cleared
 * *before* computing, so a change that lands mid-rollup marks the day again.
 */
final class RollupDay
{
    public function __construct(private readonly TenantContext $context) {}

    /** @return bool false when another process is rolling up the same day right now */
    public function handle(string $date): bool
    {
        $tenant = $this->context->require();

        return (bool) Cache::lock("analytics:rollup:{$tenant->id}:{$date}", 60)->get(function () use ($tenant, $date): bool {
            MetricDirtyDay::query()->whereDate('business_date', $date)->delete();

            $rows = $this->compute($date, $tenant->timezone);

            DB::transaction(function () use ($date, $rows, $tenant): void {
                DailyMetric::query()->whereDate('business_date', $date)->delete();
                HourlyMetric::query()->whereDate('business_date', $date)->delete();
                ProductMetric::query()->whereDate('business_date', $date)->delete();

                $base = fn (array $row) => ['id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'business_date' => $date] + $row;
                foreach (array_chunk(array_map($base, $rows['daily']), 200) as $chunk) {
                    DailyMetric::query()->insert($chunk);
                }
                foreach (array_chunk(array_map($base, $rows['hourly']), 500) as $chunk) {
                    HourlyMetric::query()->insert($chunk);
                }
                foreach (array_chunk(array_map($base, $rows['products']), 500) as $chunk) {
                    ProductMetric::query()->insert($chunk);
                }
            });

            return true;
        });
    }

    /**
     * @return array{daily: list<array<string, mixed>>, hourly: list<array<string, mixed>>, products: list<array<string, mixed>>}
     */
    private function compute(string $date, string $timezone): array
    {
        $start = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $end = $start->addDay();

        $orders = Order::query()->whereDate('business_date', $date)
            ->get(['id', 'branch_id', 'type', 'status', 'total', 'discount_total', 'customer_id', 'placed_at']);
        $counted = $orders->filter(fn (Order $o) => SalesRules::counts($o->status));
        $countedIds = $counted->pluck('id')->all();

        $items = $countedIds === [] ? collect() : OrderItem::query()->whereIn('order_id', Order::query()->whereDate('business_date', $date)->whereNotIn('status', SalesRules::EXCLUDED)->select('id'))
            ->get(['id', 'order_id', 'product_id', 'product_name', 'quantity', 'line_total']);
        $costs = $items->isEmpty() ? collect() : OrderItemCost::query()->whereIn('order_item_id', $items->pluck('id'))->pluck('cost', 'order_item_id');
        $payments = $orders->isEmpty() ? collect() : Payment::query()->where('status', PaymentAttemptStatus::Paid)
            ->whereIn('order_id', $orders->pluck('id'))->get(['order_id', 'method', 'amount', 'refunded_amount']);

        $expenses = Expense::query()->whereDate('spent_on', $date)->selectRaw('branch_id, SUM(amount) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $waste = StockMovement::query()->where('type', StockMovementType::Waste)
            ->where('created_at', '>=', $start->utc())->where('created_at', '<', $end->utc())
            ->get(['branch_id', 'quantity', 'unit_cost'])
            ->groupBy('branch_id')
            ->map(fn (Collection $rows) => (int) round($rows->sum(fn (StockMovement $m) => abs((float) $m->quantity) * ($m->unit_cost ?? 0) / 1000)));

        $orderBranch = $orders->pluck('branch_id', 'id');
        $daily = [];
        $hourly = [];
        $products = [];

        foreach (Branch::query()->pluck('id') as $branchId) {
            $mine = $counted->where('branch_id', $branchId);
            $myItems = $items->filter(fn (OrderItem $i) => $orderBranch[$i->order_id] === $branchId);
            $myPayments = $payments->filter(fn (Payment $p) => $orderBranch[$p->order_id] === $branchId);
            $labour = Payroll::cost($start->utc(), $end->utc(), $branchId);
            $cancelled = $orders->where('branch_id', $branchId)->filter(fn (Order $o) => SalesRules::isCancelled($o->status))->count();

            if ($mine->isEmpty() && $cancelled === 0 && $labour === 0 && ! isset($expenses[$branchId]) && ! isset($waste[$branchId])) {
                continue; // nothing happened in this branch today; no row keeps ranges sparse
            }

            $channels = [];
            foreach ($mine->groupBy(fn (Order $o) => $o->type->value) as $type => $group) {
                $channels[$type] = ['orders' => $group->count(), 'sales' => (int) $group->sum('total')];
            }
            $methods = [];
            foreach ($myPayments->groupBy(fn (Payment $p) => $p->method->value) as $method => $group) {
                $methods[$method] = (int) ($group->sum('amount') - $group->sum('refunded_amount'));
            }

            $daily[] = [
                'branch_id' => $branchId,
                'orders' => $mine->count(),
                'sales' => (int) $mine->sum('total'),
                'discounts' => (int) $mine->sum('discount_total'),
                'refunds' => (int) $myPayments->sum('refunded_amount'),
                'cancelled' => $cancelled,
                'items' => (int) $myItems->sum('quantity'),
                'buyers' => $mine->whereNotNull('customer_id')->pluck('customer_id')->unique()->count(),
                'channels' => json_encode((object) $channels),
                'payments' => json_encode((object) $methods),
                'cogs' => (int) $myItems->sum(fn (OrderItem $i) => $costs[$i->id] ?? 0),
                'item_lines' => $myItems->count(),
                'costed_lines' => $myItems->filter(fn (OrderItem $i) => isset($costs[$i->id]))->count(),
                'labour' => $labour,
                'expenses' => (int) ($expenses[$branchId] ?? 0),
                'waste' => (int) ($waste[$branchId] ?? 0),
                'computed_at' => now(),
            ];

            foreach ($mine->groupBy(fn (Order $o) => (int) $o->placed_at->setTimezone($timezone)->format('G')) as $hour => $group) {
                $hourly[] = ['branch_id' => $branchId, 'hour' => $hour, 'orders' => $group->count(), 'sales' => (int) $group->sum('total')];
            }

            foreach ($myItems->groupBy(fn (OrderItem $i) => ($i->product_id ?? '').'|'.$i->product_name) as $group) {
                /** @var OrderItem $first */
                $first = $group->first();
                $products[] = [
                    'branch_id' => $branchId,
                    'product_id' => $first->product_id,
                    'product_name' => $first->product_name,
                    'quantity' => (int) $group->sum('quantity'),
                    'revenue' => (int) $group->sum('line_total'),
                    'cost' => (int) $group->sum(fn (OrderItem $i) => $costs[$i->id] ?? 0),
                    'costed_quantity' => (int) $group->filter(fn (OrderItem $i) => isset($costs[$i->id]))->sum('quantity'),
                ];
            }
        }

        return ['daily' => $daily, 'hourly' => $hourly, 'products' => $products];
    }
}
