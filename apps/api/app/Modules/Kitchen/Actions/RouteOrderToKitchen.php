<?php

namespace App\Modules\Kitchen\Actions;

use App\Modules\Commerce\Models\Order;
use App\Modules\Kitchen\Enums\KitchenItemStatus;
use App\Modules\Kitchen\Events\KitchenBoardChanged;
use App\Modules\Kitchen\Models\KitchenEvent;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Kitchen\Models\KitchenStationProduct;
use Illuminate\Support\Facades\DB;

/**
 * Sends a placed order's lines to their stations (the product's station in that branch, else the
 * branch's default station). A branch without stations simply has no KDS. Idempotent.
 */
final class RouteOrderToKitchen
{
    public function handle(Order $order): int
    {
        $stations = KitchenStation::query()->where('branch_id', $order->branch_id)->where('is_active', true)->orderByDesc('is_default')->orderBy('sort')->get();

        if ($stations->isEmpty() || KitchenItem::query()->where('order_id', $order->id)->exists()) {
            return 0;
        }

        $order->loadMissing('items');
        $default = $stations->first();
        $routes = KitchenStationProduct::query()
            ->where('branch_id', $order->branch_id)
            ->whereIn('product_id', $order->items->pluck('product_id')->filter()->all())
            ->whereIn('station_id', $stations->pluck('id')->all())
            ->pluck('station_id', 'product_id');

        $created = DB::transaction(function () use ($order, $routes, $default): int {
            foreach ($order->items as $item) {
                KitchenItem::query()->create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'station_id' => $routes[$item->product_id] ?? $default->id,
                    'status' => KitchenItemStatus::Queued,
                    'quantity' => $item->quantity,
                ]);
            }

            KitchenEvent::query()->create(['order_id' => $order->id, 'type' => 'routed', 'actor_type' => 'system']);

            return $order->items->count();
        });

        DB::afterCommit(fn () => KitchenBoardChanged::dispatch($order->tenant_id, $order->branch_id));

        return $created;
    }
}
