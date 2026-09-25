<?php

namespace App\Modules\Analytics\Support;

use App\Modules\Analytics\Models\DailyMetric;
use App\Modules\Analytics\Models\ProductMetric;
use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Operations\Support\Payroll;
use App\Modules\Payments\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The report screens and exports, built from the aggregates (`Metrics`) plus a few bounded live
 * queries (customers, stock ledger). All amounts are rial; dates are tenant-local business dates.
 */
final class Reports
{
    private const SUM_FIELDS = ['orders', 'sales', 'discounts', 'refunds', 'cancelled', 'items', 'buyers', 'cogs', 'item_lines', 'costed_lines', 'labour', 'expenses', 'waste'];

    private bool $stale = false;

    public function __construct(
        private readonly Period $period,
        private readonly ?string $branchId,
        private readonly CarbonImmutable $now,
    ) {}

    public function stale(): bool
    {
        return $this->stale;
    }

    public function branchId(): ?string
    {
        return $this->branchId;
    }

    /** @return array<string, mixed> */
    public function summary(string $compareMode): array
    {
        $compare = $this->period->compare($compareMode);
        $rows = $this->rows($this->period);
        $previous = null;
        $previousTotals = null;
        if ($compare !== null) {
            $previous = $this->rows($compare);
            $previousTotals = $this->totals($previous, $compare);
        }

        $totals = $this->totals($rows, $this->period);
        $channels = [];
        $payments = [];
        foreach ($rows as $r) {
            foreach ($r->channels as $type => $c) {
                $channels[$type]['orders'] = ($channels[$type]['orders'] ?? 0) + $c['orders'];
                $channels[$type]['sales'] = ($channels[$type]['sales'] ?? 0) + $c['sales'];
            }
            foreach ($r->payments as $method => $amount) {
                $payments[$method] = ($payments[$method] ?? 0) + $amount;
            }
        }
        arsort($payments);
        uasort($channels, fn (array $a, array $b) => $b['sales'] <=> $a['sales']);

        $dailyGoal = (int) TenantSettings::get('goals.daily_sales');

        return [
            'period' => $this->describe($this->period),
            'compare' => $compare ? $this->describe($compare) : null,
            'totals' => $totals,
            'previous' => $previousTotals,
            'series' => $this->series($rows, $previous, $compare),
            'channels' => array_map(fn (string $type, array $c) => ['key' => $type, 'label' => OrderType::tryFrom($type)?->label() ?? $type] + $c, array_keys($channels), $channels),
            'payments' => array_map(fn (string $method, int $amount) => ['key' => $method, 'label' => PaymentMethod::tryFrom($method)?->label() ?? $method, 'amount' => $amount], array_keys($payments), $payments),
            'goal' => $dailyGoal > 0 ? ['daily' => $dailyGoal, 'target' => $dailyGoal * $this->period->days, 'ratio' => round($totals['sales'] / ($dailyGoal * $this->period->days), 4)] : null,
            'stale' => $this->stale,
        ];
    }

    /** @return array{products: list<array<string, mixed>>, categories: list<array<string, mixed>>, total_revenue: int, stale: bool} */
    public function products(string $sort = 'revenue'): array
    {
        $this->stale = Metrics::refresh($this->period->fromDate(), $this->period->toDate()) || $this->stale;
        $rows = Metrics::products($this->period->fromDate(), $this->period->toDate(), $this->branchId)
            ->groupBy(fn (ProductMetric $m) => $m->product_id ?? 'name:'.$m->product_name);

        $products = $rows->map(function (Collection $group, string $key): array {
            /** @var ProductMetric $first */
            $first = $group->sortByDesc('business_date')->first();
            $quantity = (int) $group->sum('quantity');
            $costed = (int) $group->sum('costed_quantity');
            $revenue = (int) $group->sum('revenue');
            $cost = (int) $group->sum('cost');

            return [
                'key' => $key,
                'product_id' => $first->product_id,
                'name' => $first->product_name,
                'quantity' => $quantity,
                'revenue' => $revenue,
                // Only meaningful when every unit sold had a recipe cost.
                'cost' => $costed === $quantity ? $cost : null,
                'margin' => $costed === $quantity ? $revenue - $cost : null,
                'margin_ratio' => $costed === $quantity && $revenue > 0 ? round(($revenue - $cost) / $revenue, 4) : null,
            ];
        })->values();

        $total = (int) $products->sum('revenue');
        $running = 0;
        $classed = $products->sortByDesc('revenue')->values()->map(function (array $p) use ($total, &$running): array {
            $running += $p['revenue'];
            $cumulative = $total > 0 ? ($running - $p['revenue']) / $total : 0;

            return $p + ['share' => $total > 0 ? round($p['revenue'] / $total, 4) : 0, 'class' => $cumulative < 0.8 ? 'A' : ($cumulative < 0.95 ? 'B' : 'C')];
        });

        // Categories from the current catalogue (a product in several categories counts in its first).
        $categoryOf = Product::query()->whereIn('id', $classed->pluck('product_id')->filter())->with('categories:id,name')->get()
            ->mapWithKeys(fn (Product $p) => [$p->id => $p->categories->first()->name ?? 'بدون دسته']);
        $categories = $classed->groupBy(fn (array $p) => $p['product_id'] ? ($categoryOf[$p['product_id']] ?? 'بدون دسته') : 'حذف‌شده از منو')
            ->map(fn (Collection $g, string $name) => ['name' => $name, 'quantity' => (int) $g->sum('quantity'), 'revenue' => (int) $g->sum('revenue'), 'share' => $total > 0 ? round($g->sum('revenue') / $total, 4) : 0])
            ->sortByDesc('revenue')->values()->all();

        $sorted = match ($sort) {
            'quantity' => $classed->sortByDesc('quantity'),
            'margin' => $classed->sortByDesc(fn (array $p) => $p['margin'] ?? PHP_INT_MIN),
            default => $classed,
        };

        return [
            'products' => $sorted->map(fn (array $p) => $p + ['category' => $p['product_id'] ? ($categoryOf[$p['product_id']] ?? 'بدون دسته') : 'حذف‌شده از منو'])->values()->all(),
            'categories' => $categories,
            'total_revenue' => $total,
            'stale' => $this->stale,
        ];
    }

    /**
     * Average sales per (Saturday-first weekday, hour): each cell is divided by how many times that
     * weekday occurs in the range, so a 10-week range and a 1-week range read the same way.
     *
     * @return array<string, mixed>
     */
    public function hours(): array
    {
        $this->stale = Metrics::refresh($this->period->fromDate(), $this->period->toDate()) || $this->stale;
        $occurrences = array_fill(0, 7, 0);
        for ($d = $this->period->from; $d->lte($this->period->to); $d = $d->addDay()) {
            $occurrences[($d->dayOfWeek + 1) % 7]++;
        }

        $cells = [];
        $profile = array_fill(0, 24, ['orders' => 0, 'sales' => 0]);
        foreach (Metrics::hourly($this->period->fromDate(), $this->period->toDate(), $this->branchId) as $m) {
            $day = ($m->business_date->dayOfWeek + 1) % 7;
            $cells[$day][$m->hour]['sales'] = ($cells[$day][$m->hour]['sales'] ?? 0) + $m->sales;
            $cells[$day][$m->hour]['orders'] = ($cells[$day][$m->hour]['orders'] ?? 0) + $m->orders;
            $profile[$m->hour]['orders'] += $m->orders;
            $profile[$m->hour]['sales'] += $m->sales;
        }

        $out = [];
        foreach ($cells as $day => $hours) {
            foreach ($hours as $hour => $c) {
                $n = max(1, $occurrences[$day]);
                $out[] = ['day' => $day, 'hour' => $hour, 'sales' => (int) round($c['sales'] / $n), 'orders' => round($c['orders'] / $n, 1)];
            }
        }
        $active = array_keys(array_filter($profile, fn (array $p) => $p['orders'] > 0));

        return [
            'cells' => $out,
            'profile' => array_map(fn (int $h) => ['hour' => $h] + $profile[$h], range(0, 23)),
            'first_hour' => $active === [] ? 8 : min($active),
            'last_hour' => $active === [] ? 22 : max($active),
            'busiest' => $out === [] ? null : collect($out)->sortByDesc('sales')->first(),
            'stale' => $this->stale,
        ];
    }

    /** @return array{branches: list<array<string, mixed>>, stale: bool} */
    public function branches(): array
    {
        $rows = $this->rows($this->period, allBranches: true)->groupBy('branch_id');
        $names = Branch::query()->pluck('name', 'id');
        $totalSales = (int) $rows->flatten()->sum('sales');

        $branches = $names->map(function (string $name, string $id) use ($rows, $totalSales): array {
            $t = $this->totals($rows->get($id, new Collection), $this->period, $id);

            return ['id' => $id, 'name' => $name, 'share' => $totalSales > 0 ? round($t['sales'] / $totalSales, 4) : 0] + $t;
        })->sortByDesc('sales')->values()->all();

        return ['branches' => $branches, 'stale' => $this->stale];
    }

    /** @return array<string, mixed> */
    public function customers(): array
    {
        [$start, $end] = [$this->period->from->utc(), $this->period->to->addDay()->utc()];
        $orders = Order::query()->whereDate('business_date', '>=', $this->period->fromDate())->whereDate('business_date', '<=', $this->period->toDate())
            ->whereNotIn('status', SalesRules::EXCLUDED)
            ->when($this->branchId, fn ($q, $id) => $q->where('branch_id', $id));

        $perCustomer = (clone $orders)->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as orders, SUM(total) as spend')->groupBy('customer_id')->get()
            ->map(fn (Order $o) => ['customer_id' => (string) $o->customer_id, 'orders' => (int) $o->getAttribute('orders'), 'spend' => (int) $o->getAttribute('spend')]);
        $buyers = $perCustomer->pluck('customer_id');
        $returning = $buyers->isEmpty() ? 0 : Order::query()->whereIn('customer_id', $buyers)
            ->whereDate('business_date', '<', $this->period->fromDate())->whereNotIn('status', SalesRules::EXCLUDED)
            ->distinct()->count('customer_id');
        $top = $perCustomer->sortByDesc('spend')->take(10)->values();
        $people = Customer::query()->whereIn('id', $top->pluck('customer_id'))->get(['id', 'name', 'phone_e164'])->keyBy('id');
        $totalOrders = (clone $orders)->count();
        $knownOrders = (int) $perCustomer->sum('orders');

        return [
            'new' => Customer::query()->where('created_at', '>=', $start)->where('created_at', '<', $end)->count(),
            'buyers' => $buyers->count(),
            'returning' => $returning,
            'repeat' => $perCustomer->where('orders', '>=', 2)->count(),
            'known_share' => $totalOrders > 0 ? round($knownOrders / $totalOrders, 4) : null,
            'average_spend' => $buyers->isEmpty() ? 0 : (int) round($perCustomer->sum('spend') / $buyers->count() / 10) * 10,
            'top' => $top->map(fn (array $c) => [
                'id' => $c['customer_id'],
                'name' => $people[$c['customer_id']]->name ?? null,
                'phone' => self::maskPhone($people[$c['customer_id']]->phone_e164 ?? null),
                'orders' => $c['orders'],
                'spend' => $c['spend'],
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function inventory(): array
    {
        [$start, $end] = [$this->period->from->utc(), $this->period->to->addDay()->utc()];
        $movements = StockMovement::query()->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->whereIn('type', [StockMovementType::Waste, StockMovementType::Purchase])
            ->when($this->branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->get(['ingredient_id', 'type', 'quantity', 'unit_cost', 'note']);
        $value = fn (StockMovement $m) => (int) round(abs((float) $m->quantity) * ($m->unit_cost ?? 0) / 1000);

        $waste = $movements->where('type', StockMovementType::Waste);
        $ingredients = Ingredient::query()->whereIn('id', $waste->pluck('ingredient_id')->unique())->get(['id', 'name', 'unit'])->keyBy('id');
        $byIngredient = $waste->groupBy('ingredient_id')->map(fn (Collection $g, string $id) => [
            'id' => $id,
            'name' => $ingredients[$id]->name ?? '—',
            'unit' => $ingredients[$id]->unit->value ?? 'pcs',
            'quantity' => round($g->sum(fn (StockMovement $m) => abs((float) $m->quantity)), 3),
            'cost' => (int) $g->sum($value),
            'entries' => $g->count(),
        ])->sortByDesc('cost')->values();

        $this->stale = Metrics::refresh($this->period->fromDate(), $this->period->toDate()) || $this->stale;
        $daily = Metrics::daily($this->period->fromDate(), $this->period->toDate(), $this->branchId);

        return [
            'consumption' => (int) $daily->sum('cogs'),
            'sales' => (int) $daily->sum('sales'),
            'waste' => (int) $waste->sum($value),
            'purchases' => (int) $movements->where('type', StockMovementType::Purchase)->sum($value),
            'waste_items' => $byIngredient->take(15)->all(),
            'reasons' => $waste->pluck('note')->filter()->countBy()->sortDesc()->take(5)
                ->map(fn (int $count, string $note) => ['note' => $note, 'count' => $count])->values()->all(),
            'stale' => $this->stale,
        ];
    }

    /* ------------------------------------------------------------------------------------ */

    /** @return Collection<int, DailyMetric> */
    private function rows(Period $period, bool $allBranches = false): Collection
    {
        $this->stale = Metrics::refresh($period->fromDate(), $period->toDate()) || $this->stale;

        return Metrics::daily($period->fromDate(), $period->toDate(), $allBranches ? null : $this->branchId)->toBase();
    }

    /**
     * Sums plus derived figures. Today's labour is taken live: open shifts keep costing money
     * without any event that would mark the day dirty.
     *
     * @param  Collection<int, DailyMetric>  $rows
     * @return array<string, int|float|null>
     */
    private function totals(Collection $rows, Period $period, ?string $branchId = null): array
    {
        $t = [];
        foreach (self::SUM_FIELDS as $field) {
            $t[$field] = (int) $rows->sum($field);
        }

        $today = $this->now->setTimezone($period->timezone)->startOfDay();
        if ($period->includes($today)) {
            $branch = $branchId ?? $this->branchId;
            $stored = (int) $rows->filter(fn (DailyMetric $r) => $r->business_date->toDateString() === $today->toDateString())->sum('labour');
            $t['labour'] += Payroll::cost($today->utc(), $today->addDay()->utc(), $branch) - $stored;
        }

        $net = $t['sales'] - $t['refunds'];
        $profit = $net - $t['cogs'] - $t['labour'] - $t['expenses'] - $t['waste'];

        return $t + [
            'net_sales' => $net,
            'average' => $t['orders'] > 0 ? (int) round($t['sales'] / $t['orders'] / 10) * 10 : 0,
            'daily_average' => (int) round($t['sales'] / max(1, $period->days) / 10) * 10,
            'profit' => $profit,
            'margin' => $net > 0 ? round($profit / $net, 4) : null,
            'prime_cost' => $net > 0 ? round(($t['cogs'] + $t['labour']) / $net, 4) : null,
            'cogs_coverage' => $t['item_lines'] > 0 ? round($t['costed_lines'] / $t['item_lines'], 4) : null,
        ];
    }

    /**
     * Daily points up to 62 days, Jalali-month buckets beyond that. The comparison is aligned by
     * offset, so "day 3 of this period" sits next to "day 3 of the previous one".
     *
     * @param  Collection<int, DailyMetric>  $rows
     * @param  Collection<int, DailyMetric>|null  $previous
     * @return array{unit: string, points: list<array<string, mixed>>}
     */
    private function series(Collection $rows, ?Collection $previous, ?Period $compare): array
    {
        $tz = $this->period->timezone;
        $byDay = $rows->groupBy(fn (DailyMetric $r) => $r->business_date->toDateString())->map(fn (Collection $g) => ['sales' => (int) $g->sum('sales'), 'orders' => (int) $g->sum('orders')]);
        $shift = $compare ? (int) $compare->from->diffInDays($this->period->from) : 0;
        $prevByDay = ($previous ?? collect())
            ->groupBy(fn (DailyMetric $r) => CarbonImmutable::parse($r->business_date->toDateString(), $tz)->addDays($shift)->toDateString())
            ->map(fn (Collection $g) => (int) $g->sum('sales'));

        $monthly = $this->period->days > 62;
        $points = [];
        for ($d = $this->period->from; $d->lte($this->period->to); $d = $d->addDay()) {
            $key = $monthly ? Period::monthKey($d, $tz) : $d->toDateString();
            $points[$key] ??= ['key' => $key, 'label' => $monthly ? Period::monthLabel($key) : $key, 'value' => 0, 'orders' => 0, 'reference' => $compare ? 0 : null];
            $points[$key]['value'] += $byDay[$d->toDateString()]['sales'] ?? 0;
            $points[$key]['orders'] += $byDay[$d->toDateString()]['orders'] ?? 0;
            if ($compare) {
                $points[$key]['reference'] += $prevByDay[$d->toDateString()] ?? 0;
            }
        }

        return ['unit' => $monthly ? 'month' : 'day', 'points' => array_values($points)];
    }

    /** @return array{from: string, to: string, days: int, from_jalali: string, to_jalali: string} */
    private function describe(Period $p): array
    {
        return ['from' => $p->fromDate(), 'to' => $p->toDate(), 'days' => $p->days, 'from_jalali' => Period::jalali($p->from, $p->timezone), 'to_jalali' => Period::jalali($p->to, $p->timezone)];
    }

    public static function maskPhone(?string $e164): ?string
    {
        if ($e164 === null || strlen($e164) < 8) {
            return null;
        }
        $local = '0'.substr($e164, 3);

        return substr($local, 0, 4).'***'.substr($local, -4);
    }
}
