<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\ModifierRecipeItem;
use App\Modules\Inventory\Models\RecipeItem;

/**
 * What one item uses: its variant's recipe plus the effects of the chosen modifiers (which may
 * remove ingredients, e.g. a milk swap), summed per ingredient and never below zero.
 */
final class RecipeCalculator
{
    /**
     * @param  list<string>  $variantIds
     * @param  list<string>  $modifierIds
     * @return array{variants: array<string, array<string, float>>, modifiers: array<string, array<string, float>>, ingredients: array<string, Ingredient>}
     */
    public static function load(array $variantIds, array $modifierIds): array
    {
        $variants = [];
        foreach (RecipeItem::query()->whereIn('variant_id', $variantIds)->get() as $item) {
            $variants[$item->variant_id][$item->ingredient_id] = (float) $item->quantity;
        }

        $modifiers = [];
        foreach (ModifierRecipeItem::query()->whereIn('modifier_id', $modifierIds)->get() as $item) {
            $modifiers[$item->modifier_id][$item->ingredient_id] = (float) $item->quantity;
        }

        $ids = [];
        foreach ([...array_values($variants), ...array_values($modifiers)] as $items) {
            $ids = [...$ids, ...array_keys($items)];
        }
        $ingredients = Ingredient::query()->whereIn('id', array_values(array_unique($ids)))->get()->keyBy('id')->all();

        return ['variants' => $variants, 'modifiers' => $modifiers, 'ingredients' => $ingredients];
    }

    /**
     * Per-item usage of a variant with modifiers, in base units.
     *
     * @param  array{variants: array<string, array<string, float>>, modifiers: array<string, array<string, float>>}  $recipes
     * @param  list<string>  $modifierIds
     * @return array<string, float>
     */
    public static function usage(array $recipes, ?string $variantId, array $modifierIds): array
    {
        $use = $variantId !== null ? ($recipes['variants'][$variantId] ?? []) : [];

        foreach ($modifierIds as $modifierId) {
            foreach ($recipes['modifiers'][$modifierId] ?? [] as $ingredientId => $qty) {
                $use[$ingredientId] = ($use[$ingredientId] ?? 0) + $qty;
            }
        }

        return array_filter(array_map(fn (float $q) => max(0, round($q, 3)), $use), fn (float $q) => $q > 0);
    }

    /**
     * @param  array<string, float>  $usage
     * @param  array<string, Ingredient>  $ingredients
     */
    public static function cost(array $usage, array $ingredients): int
    {
        $total = 0;
        foreach ($usage as $id => $qty) {
            $total += isset($ingredients[$id]) ? $ingredients[$id]->costOf($qty) : 0;
        }

        return $total;
    }
}
