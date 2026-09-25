<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InventoryException;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\IngredientStock;
use App\Modules\Inventory\Models\ModifierRecipeItem;
use App\Modules\Inventory\Models\PurchaseOrderItem;
use App\Modules\Inventory\Models\RecipeItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\Units;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Ingredients and manual stock changes: create/edit/delete, adjustments and waste, and stock
 * counts (the counted quantity replaces the book quantity via a `count` movement).
 */
final class ManageStock
{
    public function __construct(
        private readonly PostStockMovement $stock,
        private readonly AuditLogger $audit,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function saveIngredient(array $data, ?Ingredient $ingredient = null): Ingredient
    {
        $ingredient ??= new Ingredient;
        $isNew = ! $ingredient->exists;

        $ingredient->fill([
            'name' => $data['name'],
            'unit' => $isNew ? $data['unit'] : $ingredient->unit, // the base unit is fixed once stock exists
            'pack_label' => $data['pack_label'] ?? null,
            'pack_size' => $data['pack_size'] ?? null,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // An opening cost can be typed on creation (per kg / L / piece); purchases keep it current.
        if ($isNew && isset($data['cost_per_big_unit'])) {
            $ingredient->avg_cost = (int) $data['cost_per_big_unit'] * ($ingredient->unit->value === 'pcs' ? 1000 : 1);
        }

        $ingredient->save();
        $this->audit->record($isNew ? 'ingredient.created' : 'ingredient.updated', $ingredient, ['name' => $ingredient->name]);

        return $ingredient;
    }

    public function deleteIngredient(Ingredient $ingredient): void
    {
        $used = RecipeItem::query()->where('ingredient_id', $ingredient->id)->exists()
            || ModifierRecipeItem::query()->where('ingredient_id', $ingredient->id)->exists()
            || PurchaseOrderItem::query()->where('ingredient_id', $ingredient->id)->exists()
            || StockMovement::query()->where('ingredient_id', $ingredient->id)->exists();

        if ($used) {
            throw InventoryException::ingredientInUse();
        }

        $ingredient->delete();
        $this->audit->record('ingredient.deleted', $ingredient, ['name' => $ingredient->name]);
    }

    /** A signed adjustment or a waste entry (always taken out). Quantities in the entry unit. */
    public function adjust(Ingredient $ingredient, string $branchId, StockMovementType $type, float $quantity, ?string $entryUnit, ?string $note, string $actorId, ?string $key = null): StockMovement
    {
        $base = Units::toBase($ingredient, $quantity, $entryUnit);
        $signed = $type === StockMovementType::Waste ? -abs($base) : $base;

        return $this->stock->handle($ingredient->id, $branchId, $type, $signed, $key, [
            'unit_cost' => $ingredient->avg_cost,
            'note' => $note,
            'actor_type' => 'user',
            'actor_id' => $actorId,
        ]);
    }

    /**
     * A stock count: for each counted ingredient, post the difference to the book quantity.
     *
     * @param  list<array{ingredient_id: string, counted: float, unit?: ?string}>  $lines
     * @return int movements posted
     */
    public function count(string $branchId, array $lines, string $actorId, ?string $key = null): int
    {
        $ingredients = Ingredient::query()->whereIn('id', array_column($lines, 'ingredient_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($branchId, $lines, $actorId, $key, $ingredients): int {
            $posted = 0;
            foreach ($lines as $i => $line) {
                $ingredient = $ingredients->get($line['ingredient_id']);
                if ($ingredient === null) {
                    continue;
                }
                $counted = Units::toBase($ingredient, (float) $line['counted'], $line['unit'] ?? null);
                $book = (float) (IngredientStock::query()->where('ingredient_id', $ingredient->id)->where('branch_id', $branchId)->value('quantity') ?? 0);
                $diff = round($counted - $book, 3);
                if (abs($diff) < 0.001) {
                    continue;
                }
                $this->stock->handle($ingredient->id, $branchId, StockMovementType::Count, $diff, $key ? "{$key}:{$i}" : null, [
                    'unit_cost' => $ingredient->avg_cost,
                    'note' => 'انبارگردانی',
                    'actor_type' => 'user',
                    'actor_id' => $actorId,
                ]);
                $posted++;
            }

            return $posted;
        });
    }
}
