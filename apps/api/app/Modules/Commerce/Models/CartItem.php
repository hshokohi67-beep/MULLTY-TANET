<?php

namespace App\Modules\Commerce\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $cart_id
 * @property string $product_id
 * @property string $variant_id
 * @property int $quantity
 * @property ?list<string> $modifier_ids
 * @property ?string $note
 */
#[Fillable(['cart_id', 'product_id', 'variant_id', 'quantity', 'modifier_ids', 'note'])]
class CartItem extends Model
{
    use BelongsToTenant, HasUlids;

    public const MAX_QUANTITY = 50;

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'modifier_ids' => 'array'];
    }
}
