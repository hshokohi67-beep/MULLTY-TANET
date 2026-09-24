<?php

namespace App\Modules\Insights\Support;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\TableSessionRequest;
use App\Modules\Customers\Models\Customer;
use App\Modules\Kitchen\Enums\KitchenItemStatus;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Models\Payment;
use App\Support\Localization\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The management overview, computed live from orders/payments/kitchen/customers.
 * Sales = totals of orders that were not cancelled, rejected or left unpaid online, grouped by
 * business date (tenant-local). "Today" compares against the same time of day yesterday and on the
 * same weekday last week, so a 10:00 view isn't judged against a whole finished day.
 * Phase 11 replaces the live queries with aggregates behind the same shape.
 */
final class Overview
{
    public const RANGES = ['today', 'yesterday', '7d', '30d'];

    private const LATE_MINUTES = 15;

    public function __construct(
        private readonly string $timezone,
        private readonly ?string $branchId,
        private readonly CarbonImmutable $now,
    ) {}

    /** @return array<string, mixed> */
    public function kpis(string $range): array
    {
        [$from, $to] = $this->window($range);
        $days = $from->diffInDays($to) + 1;
        $current = $this->salesBetween($from, $to);

        if ($range === 'today') {
            $cutoff = $this->now->utc();
            $yesterday = $this->salesBetween($from->subDay(), $to->subDay(), $cutoff->subDay());
            $lastWeek = $this->salesBetween($from->subWeek(), $to->subWeek(), $cutoff->subWeek());
            $compare = ['yesterday' => $yesterday, 'last_week' => $lastWeek];
        } else {
            $previous = $this->salesBetween($from->subDays((int) $days), $to->subDays((int) $days));
            $compare = ['previous' => $previous];
        }

        return [
            'range' => ['key' => $range, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'current' => $current,
            'compare' => $compare,
            'cancelled' => $this->orders($from, $to)->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])->count(),
        ];
    }

    /**
     * Today (or yesterday): sales per hour, against the average of the previous 4 same weekdays.
     * 7d/30d: sales per day, against the same day of the previous period.
     *
     * @return array{unit: string, points: list<array{label: string, value: int, reference: int}>}
     */
    public function series(string $range): array
    {
        [$from, $to] = $this->window($range);

        if (in_array($range, ['today', 'yesterday'], true)) {
            $day = $this->hourly($from);
            $refs = array_fill(0, 24, 0);
            foreach ([1, 2, 3, 4] as $weeks) {
                foreach ($this->hourly($from->subWeeks($weeks)) as $h => $v) {
                    $refs[$h] += $v;
                }
            }

            // Only the hours the café is actually active, so the chart isn't mostly empty night.
            $active = array_keys(array_filter(array_map(fn ($h) => $day[$h] + $refs[$h], range(0, 23))));
            $first = $active === [] ? 8 : max(0, min($active) - 1);
            $last = $active === [] ? 22 : min(23, max($active) + 1);

            $points = [];
            for ($h = $first; $h <= $last; $h++) {
                $points[] = ['label' => (string) $h, 'value' => $day[$h], 'reference' => intdiv($refs[$h], 4)];
            }

            return ['unit' => 'hour', 'points' => $points];
        }

        $days = (int) $from->diffInDays($to) + 1;
        $current = $this->dailyTotals($from, $to);
        $previous = $this->dailyTotals($from->subDays($days), $to->subDays($days));
        $points = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->addDays($i);
            $points[] = [
                'label' => $date->toDateString(),
                'value' => $current[$date->toDateString()] ?? 0,
                'reference' => $previous[$date->subDays($days)->toDateString()] ?? 0,
            ];
        }

        return ['unit' => 'day', 'points' => $points];
    }

    /** @return list<int> daily sales for the last 14 days (oldest first), for tile sparklines */
    public function trend(): array
    {
        $to = $this->today();
        $from = $to->subDays(13);
        $totals = $this->dailyTotals($from, $to);

        return array_map(fn (int $i) => $totals[$from->addDays($i)->toDateString()] ?? 0, range(0, 13));
    }

    /** @return array<string, mixed> */
    public function live(): array
    {
        $open = Order::query()->when($this->branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->whereIn('status', [OrderStatus::Placed, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::OutForDelivery])
            ->get(['status', 'placed_at']);
        $lateBefore = $this->now->subMinutes(self::LATE_MINUTES);

        $prep = KitchenItem::query()
            ->whereNotNull('started_at')->whereNotNull('ready_at')
            ->where('ready_at', '>=', $this->today()->startOfDay()->utc())
            ->when($this->branchId, fn ($q, $id) => $q->whereIn('order_id', Order::query()->select('id')->where('branch_id', $id)))
            ->get(['started_at', 'ready_at']);
        $avgPrep = $prep->isEmpty() ? null : (int) round($prep->avg(fn (KitchenItem $i) => $i->started_at?->diffInSeconds($i->ready_at) ?? 0) / 60);

        return [
            'new' => $open->where('status', OrderStatus::Placed)->count(),
            'preparing' => $open->whereIn('status', [OrderStatus::Accepted, OrderStatus::Preparing])->count(),
            'ready' => $open->where('status', OrderStatus::Ready)->count(),
            'out_for_delivery' => $open->where('status', OrderStatus::OutForDelivery)->count(),
            'late' => $open->filter(fn (Order $o) => in_array($o->status, [OrderStatus::Placed, OrderStatus::Accepted, OrderStatus::Preparing], true) && $o->placed_at->lt($lateBefore))->count(),
            'table_calls' => TableSessionRequest::query()->where('status', 'open')
                ->when($this->branchId, fn ($q, $id) => $q->whereHas('table', fn ($t) => $t->where('branch_id', $id)))->count(),
            'kitchen_queue' => KitchenItem::query()->whereIn('status', [KitchenItemStatus::Queued, KitchenItemStatus::Preparing])
                ->when($this->branchId, fn ($q, $id) => $q->whereIn('order_id', Order::query()->select('id')->where('branch_id', $id)))->count(),
            'avg_prep_minutes' => $avgPrep,
        ];
    }

    /** @return list<array{method: string, label: string, amount: int}> net of refunds */
    public function paymentMix(string $range): array
    {
        [$from, $to] = $this->window($range);

        $rows = Payment::query()
            ->where('status', PaymentAttemptStatus::Paid)
            ->whereIn('order_id', $this->orders($from, $to)->select('id'))
            ->get(['method', 'amount', 'refunded_amount'])
            ->groupBy(fn (Payment $p) => $p->method->value);

        return array_map(fn (PaymentMethod $m) => [
            'method' => $m->value,
            'label' => $m->label(),
            'amount' => (int) (($rows->get($m->value)?->sum('amount') ?? 0) - ($rows->get($m->value)?->sum('refunded_amount') ?? 0)),
        ], [PaymentMethod::Cash, PaymentMethod::CardPos, PaymentMethod::Online, PaymentMethod::Wallet]);
    }

    /** @return list<array{name: string, quantity: int, revenue: int}> */
    public function topProducts(string $range, int $limit = 5): array
    {
        [$from, $to] = $this->window($range);

        return OrderItem::query()
            ->whereIn('order_id', $this->counted($this->orders($from, $to))->select('id'))
            ->selectRaw('product_name as name, SUM(quantity) as quantity, SUM(line_total) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('quantity')->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['name' => (string) $r->getAttribute('name'), 'quantity' => (int) $r->getAttribute('quantity'), 'revenue' => (int) $r->getAttribute('revenue')])
            ->values()->all();
    }

    /** @return array<string, mixed> */
    public function customers(string $range): array
    {
        [$from, $to] = $this->window($range);
        $start = $from->startOfDay()->utc();
        $end = $to->endOfDay()->utc();

        $buyers = $this->counted($this->orders($from, $to))->whereNotNull('customer_id')->distinct()->pluck('customer_id');
        $returning = $buyers->isEmpty() ? 0 : Order::query()->whereIn('customer_id', $buyers)
            ->where('business_date', '<', $from->toDateString())
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected, OrderStatus::PendingPayment])
            ->distinct()->count('customer_id');

        return [
            'new' => Customer::query()->whereBetween('created_at', [$start, $end])->count(),
            'buyers' => $buyers->count(),
            'returning' => $returning,
            'birthdays' => $this->birthdays(7),
        ];
    }

    /**
     * Customers whose Jalali birthday falls in the next $days days (today included), soonest first.
     *
     * @return list<array{id: string, name: ?string, month: int, day: int, in_days: int}>
     */
    public function birthdays(int $days): array
    {
        $upcoming = [];
        for ($i = 0; $i < $days; $i++) {
            $j = JalaliDate::toJalali($this->today()->addDays($i), $this->timezone);
            $upcoming[$j['month'].'-'.$j['day']] = $i;
        }

        $months = array_unique(array_map(fn (string $k) => (int) explode('-', $k)[0], array_keys($upcoming)));

        return Customer::query()->whereIn('birth_month', $months)->whereNotNull('birth_day')->limit(200)->get(['id', 'name', 'birth_month', 'birth_day'])
            ->filter(fn (Customer $c) => isset($upcoming[$c->birth_month.'-'.$c->birth_day]))
            ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name, 'month' => (int) $c->birth_month, 'day' => (int) $c->birth_day, 'in_days' => $upcoming[$c->birth_month.'-'.$c->birth_day]])
            ->sortBy('in_days')->values()->all();
    }

    /* ------------------------------------------------------------------------ */

    public function today(): CarbonImmutable
    {
        return $this->now->setTimezone($this->timezone)->startOfDay();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} business dates (tenant-local midnight) */
    public function window(string $range): array
    {
        $today = $this->today();

        return match ($range) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            '7d' => [$today->subDays(6), $today],
            '30d' => [$today->subDays(29), $today],
            default => [$today, $today],
        };
    }

    /** @return Builder<Order> */
    public function orders(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->when($this->branchId, fn ($q, $id) => $q->where('branch_id', $id));
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function counted(Builder $query): Builder
    {
        return $query->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected, OrderStatus::PendingPayment]);
    }

    /** @return array{sales: int, orders: int, average: int, discounts: int} */
    private function salesBetween(CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $placedBefore = null): array
    {
        $q = $this->counted($this->orders($from, $to))->when($placedBefore, fn ($q, $at) => $q->where('placed_at', '<=', $at));
        $orders = (clone $q)->count();
        $sales = (int) (clone $q)->sum('total');

        return [
            'sales' => $sales,
            'orders' => $orders,
            'average' => $orders > 0 ? (int) round($sales / $orders / 10) * 10 : 0,
            'discounts' => (int) (clone $q)->sum('discount_total'),
        ];
    }

    /** @return array<string, int> business date → sales */
    private function dailyTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->counted($this->orders($from, $to))
            ->selectRaw('business_date, SUM(total) as sales')
            ->groupBy('business_date')
            ->get()
            ->mapWithKeys(fn (Order $o) => [$o->business_date->toDateString() => (int) $o->getAttribute('sales')])
            ->all();
    }

    /** @return array<int, int> hour (tenant-local) → sales for one business date */
    private function hourly(CarbonImmutable $day): array
    {
        $buckets = array_fill(0, 24, 0);

        /** @var Collection<int, Order> $orders */
        $orders = $this->counted($this->orders($day, $day))->get(['placed_at', 'total']);
        foreach ($orders as $order) {
            $buckets[(int) $order->placed_at->setTimezone($this->timezone)->format('G')] += $order->total;
        }

        return $buckets;
    }
}
