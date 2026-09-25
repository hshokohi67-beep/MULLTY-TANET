<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ingredient of a product variant's recipe (base units per one item).
 *
 * @property string $id
 * @property string $variant_id
 * @property string $ingredient_id
 * @property string $quantity
 * @property Ingredient $ingredient
 */
#[Fillable(['variant_id', 'ingredient_id', 'quantity'])]
class RecipeItem extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
