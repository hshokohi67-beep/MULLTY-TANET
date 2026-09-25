<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\StockMovementType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only stock ledger row. Written only by PostStockMovement.
 *
 * @property string $id
 * @property string $ingredient_id
 * @property string $branch_id
 * @property StockMovementType $type
 * @property string $quantity
 * @property string $balance_after
 * @property ?int $unit_cost
 * @property ?string $order_id
 * @property ?string $order_item_id
 * @property ?string $purchase_order_id
 * @property ?string $note
 * @property string $actor_type
 * @property ?string $actor_id
 * @property ?string $idempotency_key
 * @property Carbon $created_at
 * @property Ingredient $ingredient
 */
#[Fillable(['ingredient_id', 'branch_id', 'type', 'quantity', 'balance_after', 'unit_cost', 'order_id', 'order_item_id', 'purchase_order_id', 'note', 'actor_type', 'actor_id', 'idempotency_key'])]
class StockMovement extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost' => 'integer',
        ];
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
