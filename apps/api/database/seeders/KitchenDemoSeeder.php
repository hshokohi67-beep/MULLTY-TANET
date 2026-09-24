<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Branch;
use App\Modules\Kitchen\Actions\ManageStations;
use App\Modules\Kitchen\Actions\RouteOrderToKitchen;
use App\Modules\Kitchen\Enums\KitchenItemStatus;
use App\Modules\Kitchen\Models\KitchenItem;
use Illuminate\Database\Seeder;

/**
 * Two stations in the main branch («بار قهوه» for drinks, «آشپزخانه» ("kitchen") for cakes and
 * breakfast), and the open demo orders sent to them with item states matching the order status.
 */
class KitchenDemoSeeder extends Seeder
{
    public function run(ManageStations $stations, RouteOrderToKitchen $route): void
    {
        $branch = Branch::query()->where('slug', 'main')->firstOrFail();
        $stations->save(null, ['branch_id' => $branch->id, 'name' => 'بار قهوه', 'is_default' => true, 'late_after_minutes' => 7]);
        $kitchen = $stations->save(null, ['branch_id' => $branch->id, 'name' => 'آشپزخانه', 'late_after_minutes' => 12, 'sort' => 1]);

        $food = Category::query()->whereIn('name', ['کیک و دسر', 'صبحانه'])->pluck('id');
        $foodProducts = Category::query()->whereKey($food)->with('products')->get()->flatMap->products->pluck('id')->unique()->values()->all();
        $stations->syncProducts($kitchen, $foodProducts);

        Order::query()->whereIn('status', [OrderStatus::Placed, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready])->get()
            ->each(function (Order $order) use ($route): void {
                $route->handle($order);

                $status = match ($order->status) {
                    OrderStatus::Preparing => KitchenItemStatus::Preparing,
                    OrderStatus::Ready => KitchenItemStatus::Ready,
                    default => KitchenItemStatus::Queued,
                };

                if ($status !== KitchenItemStatus::Queued) {
                    KitchenItem::query()->where('order_id', $order->id)->update(['status' => $status, 'started_at' => now(), 'ready_at' => $status === KitchenItemStatus::Ready ? now() : null]);
                }
            });
    }
}
