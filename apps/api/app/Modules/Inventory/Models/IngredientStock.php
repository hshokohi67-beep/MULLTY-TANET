<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $ingredient_id
 * @property string $branch_id
 * @property string $quantity
 */
#[Fillable(['ingredient_id', 'branch_id', 'quantity'])]
class IngredientStock extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }
}
