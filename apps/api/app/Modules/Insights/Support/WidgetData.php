<?php

namespace App\Modules\Insights\Support;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderSession;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountUsage;
use App\Modules\Insights\Models\ShiftNote;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\Wallet;
use App\Modules\Loyalty\Models\WalletTransaction;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Models\Payment;
use App\Modules\Storefront\Models\Story;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Data for the widgets beyond the core overview. Each public method is one widget and returns a
 * small, display-ready array (amounts in rial). Live queries for now; Phase 11 aggregates later.
 */
final class WidgetData
{
    private readonly Overview $overview;

    public function __construct(
        private readonly string $timezone,
        private readonly ?string $branchId,
        private readonly CarbonImmutable $now,
    ) {
        $this->overview = new Overview($timezone, $branchId, $now);
    }

    /** @return array<string, mixed> */
    public function get(string $key, string $range): array
    {
        return match ($key) {
            'goal' => $this->goal(),
            'channel_mix' => $this->channelMix($range),
            'heatmap' => $this->heatmap(),
            'tables_now' => $this->tablesNow(),
            'kitchen_speed' => $this->kitchenSpeed($range),
            'cancellations' => $this->cancellations($range),
            'branches' => $this->branches($range),
            'payment_health' => $this->paymentHealth($range),
            'at_risk' => $this->atRisk(),
            'club_liability' => $this->clubLiability($range),
            'discounts' => $this->discounts($range),
            'shift_notes' => $this->shiftNotes(),
            'stories' => $this->stories(),
            default => [],
        };
    }

    /** Today vs the daily goal; month-to-date vs the monthly goal, with a straight-line projection.
     *
     * @return array<string, mixed>
     */
    public function goal(): array
    {
        $today = $this->overview->today();
        $monthStart = $today->startOfMonth();
        $sales = fn (CarbonImmutable $from, CarbonImmutable $to) => (int) $this->overview->counted($this->overview->orders($from, $to))->sum('total');
        $mtd = $sales($monthStart, $today);
        $dayOfMonth = (int) $today->day;
        $daysInMonth = (int) $today->daysInMonth;

        return [
            'daily_goal' => (int) TenantSettings::get('goals.daily_sales'),
            'today' => $sales($today, $today),
            'monthly_goal' => (int) TenantSettings::get('goals.monthly_sales'),
            'month_to_date' => $mtd,
            'projection' => $dayOfMonth > 0 ? intdiv($mtd * $daysInMonth, $dayOfMonth) : 0,
            'days_left' => $daysInMonth - $dayOfMonth,
        ];
    }

    /** @return array{channels: list<array{type: string, label: string, sales: int, orders: int}>} */
    public function channelMix(string $range): array
    {
        [$from, $to] = $this->overview->window($range);
        $rows = $this->overview->counted($this->overview->orders($from, $to))
            ->selectRaw('type, COUNT(*) as orders, SUM(total) as sales')->groupBy('type')->get()
            ->keyBy(fn (Order $o) => $o->type->value);

        $channels = [];
        foreach (OrderType::cases() as $type) {
            $row = $rows->get($type->value);
            if ($row !== null) {
                $channels[] = ['type' => $type->value, 'label' => $type->label(), 'sales' => (int) $row->getAttribute('sales'), 'orders' => (int) $row->getAttribute('orders')];
            }
        }
        usort($channels, fn (array $a, array $b) => $b['sales'] <=> $a['sales']);

        return ['channels' => $channels];
    }

    /**
     * Sales per (Saturday-first weekday, local hour) over the last 28 days, averaged per week.
     *
     * @return array{cells: list<array{day: int, hour: int, sales: int}>, max: int, first_hour: int, last_hour: int}
     */
    public function heatmap(): array
    {
        $to = $this->overview->today();
        $from = $to->subDays(27);
        $grid = [];
        $orders = $this->overview->counted($this->overview->orders($from, $to))->get(['placed_at', 'total']);

        foreach ($orders as $order) {
            $local = $order->placed_at->setTimezone($this->timezone);
            $day = ((int) $local->format('N') + 1) % 7; // Saturday = 0 … Friday = 6
            $hour = (int) $local->format('G');
            $grid[$day][$hour] = ($grid[$day][$hour] ?? 0) + $order->total;
        }

        $cells = [];
        $hours = [];
        foreach ($grid as $day => $byHour) {
            foreach ($byHour as $hour => $sum) {
                $cells[] = ['day' => $day, 'hour' => $hour, 'sales' => intdiv($sum, 4)];
                $hours[] = $hour;
            }
        }

        return [
            'cells' => $cells,
            'max' => $cells === [] ? 0 : max(array_column($cells, 'sales')),
            'first_hour' => $hours === [] ? 8 : min($hours),
            'last_hour' => $hours === [] ? 22 : max($hours),
        ];
    }

    /** Occupied tables now, average sitting time today, dine-in sales per seat today.
     *
     * @return array<string, mixed>
     */
    public function tablesNow(): array
    {
        $tables = RestaurantTable::query()->where('is_active', true)->when($this->branchId, fn ($q, $id) => $q->where('branch_id', $id))->get(['id', 'capacity']);
        $open = OrderSession::query()->where('status', 'open')->whereIn('table_id', $tables->pluck('id'))->distinct()->count('table_id');
        $dayStart = $this->overview->today()->utc();

        $closed = OrderSession::query()->whereIn('table_id', $tables->pluck('id'))->whereNotNull('closed_at')->where('opened_at', '>=', $dayStart)->get(['opened_at', 'closed_at']);
        $avgMinutes = $closed->isEmpty() ? null : (int) round($closed->avg(fn (OrderSession $s) => $s->opened_at->diffInMinutes($s->closed_at)));

        $today = $this->overview->today();
        $dineIn = (int) $this->overview->counted($this->overview->orders($today, $today))->whereIn('type', [OrderType::QrTable, OrderType::DineIn])->sum('total');
        $seats = (int) $tables->sum(fn (RestaurantTable $t) => $t->capacity ?? 2);

        return [
            'tables' => $tables->count(),
            'occupied' => $open,
            'seats' => $seats,
            'avg_sitting_minutes' => $avgMinutes,
            'dine_in_sales' => $dineIn,
            'sales_per_seat' => $seats > 0 ? intdiv($dineIn, $seats) : 0,
        ];
    }

    /** @return array{stations: list<array{name: string, items: int, avg_minutes: ?int, late_share: ?float}>} */
    public function kitchenSpeed(string $range): array
    {
        [$from, $to] = $this->overview->window($range);
        $orders = $this->overview->orders($from, $to)->select('id');
        $stations = KitchenStation::query()->when($this->branchId, fn ($q, $id) => $q->where('branch_id', $id))->get(['id', 'name', 'late_after_minutes'])->keyBy('id');

        $items = KitchenItem::query()->whereIn('order_id', $orders)->whereNotNull('ready_at')->with('order:id,placed_at')->get(['station_id', 'order_id', 'started_at', 'ready_at']);

        $out = [];
        foreach ($items->groupBy('station_id') as $stationId => $group) {
            $station = $stations->get($stationId);
            if ($station === null) {
                continue;
            }
            $prep = $group->filter(fn (KitchenItem $i) => $i->started_at !== null)->map(fn (KitchenItem $i) => $i->started_at?->diffInSeconds($i->ready_at) ?? 0);
            $late = $group->filter(fn (KitchenItem $i) => $i->order->placed_at->diffInMinutes($i->ready_at) > $station->late_after_minutes)->count();
            $out[] = [
                'name' => $station->name,
                'items' => $group->count(),
                'avg_minutes' => $prep->isEmpty() ? null : (int) round($prep->avg() / 60),
                'late_share' => round($late / max(1, $group->count()), 3),
            ];
        }

        return ['stations' => $out];
    }

    /** @return array{cancelled: int, rejected: int, total_orders: int, reasons: list<array{reason: string, count: int}>} */
    public function cancellations(string $range): array
    {
        [$from, $to] = $this->overview->window($range);
        $base = $this->overview->orders($from, $to);
        $closed = (clone $base)->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])->get(['status', 'cancel_reason']);

        $reasons = $closed->groupBy(fn (Order $o) => trim((string) $o->cancel_reason) ?: 'بدون دلیل')
            ->map(fn ($g, $reason) => ['reason' => (string) $reason, 'count' => $g->count()])
            ->sortByDesc('count')->take(5)->values()->all();

        return [
            'cancelled' => $closed->where('status', OrderStatus::Cancelled)->count(),
            'rejected' => $closed->where('status', OrderStatus::Rejected)->count(),
            'total_orders' => (clone $base)->count(),
            'reasons' => $reasons,
        ];
    }

    /** @return array{branches: list<array{id: string, name: string, sales: int, orders: int, average: int}>} */
    public function branches(string $range): array
    {
        [$from, $to] = $this->overview->window($range);
        $rows = $this->overview->counted(Order::query()->whereBetween('business_date', [$from->toDateString(), $to->toDateString()]))
            ->selectRaw('branch_id, COUNT(*) as orders, SUM(total) as sales')->groupBy('branch_id')->get()->keyBy('branch_id');

        $branches = Branch::query()->orderBy('name')->get(['id', 'name'])->map(function (Branch $b) use ($rows): array {
            $orders = (int) ($rows->get($b->id)?->getAttribute('orders') ?? 0);
            $sales = (int) ($rows->get($b->id)?->getAttribute('sales') ?? 0);

            return ['id' => $b->id, 'name' => $b->name, 'sales' => $sales, 'orders' => $orders, 'average' => $orders > 0 ? (int) round($sales / $orders / 10) * 10 : 0];
        })->sortByDesc('sales')->values()->all();

        return ['branches' => $branches];
    }

    /** Online payment attempts: success rate, failures, expiries, and orders cancelled because nobody paid.
     *
     * @return array<string, mixed>
     */
    public function paymentHealth(string $range): array
    {
        [$from, $to] = $this->overview->window($range);
        $orders = $this->overview->orders($from, $to);
        $attempts = Payment::query()->where('method', PaymentMethod::Online)->whereIn('order_id', (clone $orders)->select('id'))->get(['status']);
        $finished = $attempts->whereIn('status', [PaymentAttemptStatus::Paid, PaymentAttemptStatus::Failed, PaymentAttemptStatus::Expired]);

        return [
            'attempts' => $attempts->count(),
            'paid' => $attempts->where('status', PaymentAttemptStatus::Paid)->count(),
            'failed' => $attempts->where('status', PaymentAttemptStatus::Failed)->count(),
            'expired' => $attempts->where('status', PaymentAttemptStatus::Expired)->count(),
            'pending' => $attempts->where('status', PaymentAttemptStatus::Pending)->count(),
            'success_rate' => $finished->isEmpty() ? null : round($attempts->where('status', PaymentAttemptStatus::Paid)->count() / $finished->count(), 3),
            'abandoned_orders' => (clone $orders)->where('status', OrderStatus::Cancelled)->where('payment_method_intent', 'online')->where('paid_total', 0)->count(),
        ];
    }

    /**
     * Regulars (3+ counted orders) who are overdue: absent for more than twice their usual gap and
     * at least 14 days. Also the typical repeat interval across regulars.
     *
     * @return array<string, mixed>
     */
    public function atRisk(): array
    {
        $today = $this->overview->today();
        $orders = Order::query()->whereNotNull('customer_id')
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected, OrderStatus::PendingPayment])
            ->where('business_date', '>=', $today->subDays(365)->toDateString())
            ->orderBy('business_date')
            ->get(['customer_id', 'business_date', 'contact_name']);

        $gaps = [];
        $risky = [];
        foreach ($orders->groupBy('customer_id') as $customerId => $list) {
            $dates = $list->map(fn (Order $o) => $o->business_date->toDateString())->unique()->values();
            if ($dates->count() < 3) {
                continue;
            }
            $intervals = [];
            for ($i = 1; $i < $dates->count(); $i++) {
                $intervals[] = CarbonImmutable::parse($dates[$i - 1])->diffInDays(CarbonImmutable::parse($dates[$i]));
            }
            $usual = array_sum($intervals) / count($intervals);
            $gaps[] = $usual;
            $since = (int) CarbonImmutable::parse((string) $dates->last())->diffInDays($today);

            if ($since >= 14 && $since > 2 * $usual) {
                $risky[] = ['customer_id' => (string) $customerId, 'name' => $list->last()?->contact_name, 'orders' => $list->count(), 'days_since' => $since, 'usual_gap' => (int) round($usual)];
            }
        }
        usort($risky, fn (array $a, array $b) => $b['orders'] <=> $a['orders']);

        return [
            'regulars' => count($gaps),
            'typical_gap_days' => $gaps === [] ? null : (int) round(array_sum($gaps) / count($gaps)),
            'at_risk' => count($risky),
            'customers' => array_slice($risky, 0, 8),
        ];
    }

    /** What the café owes its members, and cashback movement in the range.
     *
     * @return array<string, mixed>
     */
    public function clubLiability(string $range): array
    {
        [$from, $to] = $this->overview->window($range);
        $start = $from->startOfDay()->utc();
        $end = $to->endOfDay()->utc();
        $pointValue = (int) TenantSettings::get('loyalty.point_value');
        $points = (int) LoyaltyAccount::query()->where('points', '>', 0)->sum('points');
        $moves = WalletTransaction::query()->whereBetween('created_at', [$start, $end])->get(['type', 'amount']);

        return [
            'wallet_owed' => (int) Wallet::query()->where('balance', '>', 0)->sum('balance'),
            'wallets' => Wallet::query()->where('balance', '>', 0)->count(),
            'points' => $points,
            'points_value' => $points * $pointValue,
            'cashback_issued' => (int) $moves->whereIn('type', [WalletTransactionType::Cashback, WalletTransactionType::Birthday, WalletTransactionType::Referral])->sum('amount'),
            'spent_from_wallet' => (int) -$moves->where('type', WalletTransactionType::OrderPayment)->sum('amount'),
        ];
    }

    /** @return array{discounts: list<array{name: string, code: ?string, uses: int, given: int, revenue: int}>} */
    public function discounts(string $range): array
    {
        [$from, $to] = $this->overview->window($range);
        $orderIds = $this->overview->counted($this->overview->orders($from, $to))->select('id');
        $usages = DiscountUsage::query()->whereIn('order_id', $orderIds)->get(['discount_id', 'order_id', 'amount'])->groupBy('discount_id');
        $totals = Order::query()->whereIn('id', $usages->flatten()->pluck('order_id'))->pluck('total', 'id');
        $names = Discount::query()->whereKey($usages->keys())->get(['id', 'name', 'code'])->keyBy('id');

        $rows = $usages->map(fn ($list, $id) => [
            'name' => $names->get($id)->name ?? '—',
            'code' => $names->get($id)?->code,
            'uses' => $list->count(),
            'given' => (int) $list->sum('amount'),
            'revenue' => (int) $list->sum(fn ($u) => (int) ($totals[$u->order_id] ?? 0)),
        ])->sortByDesc('uses')->values()->all();

        return ['discounts' => $rows];
    }

    /**
     * Stories live now or ended in the last 7 days, with their (lifetime) views and clicks.
     *
     * @return array{live: int, stories: list<array{id: string, thumb_url: string, caption: ?string, status: string, views: int, clicks: int, ends_at: string}>}
     */
    public function stories(): array
    {
        $disk = Storage::disk(config('filesystems.media_disk'));
        $rows = Story::query()->where('ends_at', '>', $this->now->subDays(7))
            ->when($this->branchId, fn ($q, $id) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $id)))
            ->orderByDesc('views')->limit(6)->get();

        return [
            'live' => Story::query()->live()->count(),
            'stories' => $rows->map(fn (Story $s) => [
                'id' => $s->id,
                'thumb_url' => $disk->url($s->thumb_path),
                'caption' => $s->caption,
                'status' => $s->status(),
                'views' => $s->views,
                'clicks' => $s->clicks,
                'ends_at' => $s->ends_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** @return array{notes: list<array{id: string, body: string, author: string, author_id: string, created_at: string}>} */
    public function shiftNotes(): array
    {
        return ['notes' => ShiftNote::query()->with('author:id,name')
            ->when($this->branchId, fn ($q, $id) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $id)))
            ->where('created_at', '>=', $this->now->subDays(7))
            ->latest()->limit(10)->get()
            ->map(fn (ShiftNote $n) => ['id' => $n->id, 'body' => $n->body, 'author' => $n->author->name, 'author_id' => $n->author_id, 'created_at' => $n->created_at->toIso8601String()])
            ->values()->all()];
    }
}
