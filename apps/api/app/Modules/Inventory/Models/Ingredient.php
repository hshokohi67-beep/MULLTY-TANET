<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\IngredientUnit;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A raw material (coffee beans, milk, cups…). Quantities are in the base unit; avg_cost is rial
 * per 1000 base units.
 *
 * @property string $id
 * @property string $name
 * @property IngredientUnit $unit
 * @property ?string $pack_label
 * @property ?string $pack_size
 * @property int $avg_cost
 * @property string $low_stock_threshold
 * @property bool $is_active
 */
#[Fillable(['name', 'unit', 'pack_label', 'pack_size', 'avg_cost', 'low_stock_threshold', 'is_active'])]
class Ingredient extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'unit' => IngredientUnit::class,
            'avg_cost' => 'integer',
            'pack_size' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<IngredientStock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(IngredientStock::class);
    }

    /** Cost in rial of a quantity in base units, at the current average cost. */
    public function costOf(float $quantity): int
    {
        return (int) round($quantity * $this->avg_cost / 1000);
    }
}
