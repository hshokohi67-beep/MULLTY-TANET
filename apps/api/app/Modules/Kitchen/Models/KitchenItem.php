<?php

namespace App\Modules\Kitchen\Models;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Kitchen\Enums\KitchenItemStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One order line as the kitchen sees it, at one station.
 *
 * @property string $id
 * @property string $order_id
 * @property string $order_item_id
 * @property string $station_id
 * @property KitchenItemStatus $status
 * @property int $quantity
 * @property ?Carbon $started_at
 * @property ?Carbon $ready_at
 * @property Carbon $updated_at
 * @property Order $order
 * @property OrderItem $orderItem
 * @property KitchenStation $station
 */
#[Fillable(['order_id', 'order_item_id', 'station_id', 'status', 'quantity', 'started_at', 'ready_at'])]
class KitchenItem extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['status' => KitchenItemStatus::class, 'quantity' => 'integer', 'started_at' => 'datetime', 'ready_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<KitchenStation, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }
}
