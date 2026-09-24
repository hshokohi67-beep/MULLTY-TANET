<?php

namespace App\Modules\Kitchen\Support;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItemModifier;
use App\Modules\Commerce\Models\TableSessionRequest;
use App\Modules\Kitchen\Enums\KitchenItemStatus;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use Illuminate\Support\Collection;

/**
 * Everything a kitchen screen shows, recomputed on each fetch (screens are stateless, so a tablet
 * that reconnects is instantly correct). Open items, plus orders that finished or were cancelled
 * in the last two minutes so the cook sees them leave rather than vanish.
 */
final class KitchenBoard
{
    public const RECENT_SECONDS = 120;

    /**
     * @param  list<string>  $stationIds
     * @return array<string, mixed>
     */
    public static function build(string $branchId, array $stationIds): array
    {
        $stations = KitchenStation::query()->where('branch_id', $branchId)->whereKey($stationIds)->orderBy('sort')->get();
        $recent = now()->subSeconds(self::RECENT_SECONDS);

        $items = KitchenItem::query()
            ->with(['orderItem.modifiers', 'station'])
            ->whereIn('station_id', $stations->pluck('id')->all())
            ->where(fn ($q) => $q->whereIn('status', [KitchenItemStatus::Queued, KitchenItemStatus::Preparing])->orWhere('updated_at', '>=', $recent))
            ->orderBy('created_at')->orderBy('id')
            ->get();

        $orders = Order::query()->with('table')->whereKey($items->pluck('order_id')->unique()->all())->get()->keyBy('id');
        $tiers = LoyaltyAccount::query()->with('tier')->whereIn('customer_id', $orders->pluck('customer_id')->filter()->unique()->all())->get()
            ->mapWithKeys(fn (LoyaltyAccount $a) => [$a->customer_id => $a->tier?->name]);

        $cards = $items->groupBy('order_id')->map(function (Collection $orderItems, string $orderId) use ($orders, $tiers): array {
            /** @var Order $order */
            $order = $orders[$orderId];
            $open = $orderItems->contains(fn (KitchenItem $i) => in_array($i->status, [KitchenItemStatus::Queued, KitchenItemStatus::Preparing], true));
            $cancelled = $orderItems->every(fn (KitchenItem $i) => $i->status === KitchenItemStatus::Cancelled);

            return [
                'order_id' => $order->id,
                'daily_number' => $order->daily_number,
                'type' => $order->type->value,
                'type_label' => $order->type->label(),
                'table' => $order->table?->label,
                'customer_name' => $order->contact_name,
                'tier' => $order->customer_id ? ($tiers[$order->customer_id] ?? null) : null,
                'note' => $order->customer_note,
                'scheduled_for' => $order->scheduled_for?->toIso8601String(),
                'placed_at' => $order->placed_at->toIso8601String(),
                'order_status' => $order->status->value,
                'state' => $cancelled ? 'cancelled' : ($open ? 'open' : 'done'),
                'items' => $orderItems->map(fn (KitchenItem $i) => [
                    'id' => $i->id,
                    'station_id' => $i->station_id,
                    'station' => $i->station->name,
                    'name' => $i->orderItem->product_name,
                    'variant' => $i->orderItem->variant_name,
                    'quantity' => $i->quantity,
                    'modifiers' => $i->orderItem->modifiers->map(fn (OrderItemModifier $m) => $m->name)->values(),
                    'note' => $i->orderItem->note,
                    'status' => $i->status->value,
                    'started_at' => $i->started_at?->toIso8601String(),
                    'ready_at' => $i->ready_at?->toIso8601String(),
                ])->values(),
            ];
        })->values()
            // Scheduled orders show when due; otherwise oldest first (FIFO, like the legacy board).
            ->sortBy(fn (array $c) => $c['scheduled_for'] ?? $c['placed_at'])->values();

        $calls = TableSessionRequest::query()->with('table')->where('status', 'open')
            ->whereHas('table', fn ($q) => $q->where('branch_id', $branchId))
            ->oldest('created_at')->limit(30)->get()
            ->map(fn (TableSessionRequest $r) => [
                'id' => $r->id,
                'type' => $r->type->value,
                'type_label' => $r->type->label(),
                'table' => $r->table->label,
                'created_at' => $r->created_at->toIso8601String(),
            ])->values();

        return [
            'stations' => $stations->map(fn (KitchenStation $s) => ['id' => $s->id, 'name' => $s->name, 'late_after_minutes' => $s->late_after_minutes])->values(),
            'orders' => $cards,
            'table_requests' => $calls,
        ];
    }
}
