<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * The cost of one sold order line at the moment of sale.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string $order_item_id
 * @property int $cost
 * @property list<array{ingredient_id: string, name: string, quantity: float, cost: int}> $breakdown
 */
#[Fillable(['order_id', 'order_item_id', 'cost', 'breakdown'])]
class OrderItemCost extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['cost' => 'integer', 'breakdown' => 'array'];
    }
}
