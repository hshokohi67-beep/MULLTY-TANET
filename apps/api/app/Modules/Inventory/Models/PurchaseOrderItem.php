<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $purchase_order_id
 * @property string $ingredient_id
 * @property string $quantity
 * @property string $received_quantity
 * @property int $unit_price rial per 1000 base units
 * @property int $line_total
 * @property Ingredient $ingredient
 */
#[Fillable(['purchase_order_id', 'ingredient_id', 'quantity', 'received_quantity', 'unit_price', 'line_total'])]
class PurchaseOrderItem extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'received_quantity' => 'decimal:3', 'unit_price' => 'integer', 'line_total' => 'integer'];
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
