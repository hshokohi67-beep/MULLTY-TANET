<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\IngredientStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An ingredient with its stock per branch. Costs: `avg_cost` is rial per 1000 base units;
 * `cost_per_big_unit` is what people read (per kg, per litre, per piece).
 *
 * @mixin Ingredient
 */
final class IngredientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $stocks = $this->relationLoaded('stocks') ? $this->stocks : collect();
        $total = round((float) $stocks->sum(fn (IngredientStock $s) => (float) $s->quantity), 3);
        $threshold = (float) $this->low_stock_threshold;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit->value,
            'unit_label' => $this->unit->label(),
            'big_unit_label' => $this->unit->bigLabel(),
            'pack_label' => $this->pack_label,
            'pack_size' => $this->pack_size !== null ? (float) $this->pack_size : null,
            'avg_cost' => $this->avg_cost,
            'cost_per_big_unit' => $this->unit->value === 'pcs' ? (int) round($this->avg_cost / 1000) : $this->avg_cost,
            'low_stock_threshold' => $threshold,
            'is_active' => $this->is_active,
            'stocks' => $stocks->map(fn (IngredientStock $s) => [
                'branch_id' => $s->branch_id,
                'quantity' => (float) $s->quantity,
                'is_low' => $threshold > 0 && (float) $s->quantity <= $threshold,
            ])->values(),
            'total_quantity' => $total,
            'stock_value' => max(0, $this->costOf(max(0, $total))),
            'is_low' => $threshold > 0 && $stocks->contains(fn (IngredientStock $s) => (float) $s->quantity <= $threshold),
            'is_negative' => $stocks->contains(fn (IngredientStock $s) => (float) $s->quantity < 0),
        ];
    }
}
