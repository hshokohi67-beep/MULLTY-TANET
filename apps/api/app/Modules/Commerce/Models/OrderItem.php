<?php

namespace App\Modules\Commerce\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $order_id
 * @property ?string $product_id
 * @property ?string $variant_id
 * @property string $product_name
 * @property ?string $variant_name
 * @property int $unit_price
 * @property int $modifiers_total
 * @property int $quantity
 * @property int $line_total
 * @property ?string $note
 */
#[Fillable(['order_id', 'product_id', 'variant_id', 'product_name', 'variant_name', 'unit_price', 'modifiers_total', 'quantity', 'line_total', 'note'])]
class OrderItem extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'modifiers_total' => 'integer', 'quantity' => 'integer', 'line_total' => 'integer'];
    }

    /** @return HasMany<OrderItemModifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class)->orderBy('id');
    }
}
