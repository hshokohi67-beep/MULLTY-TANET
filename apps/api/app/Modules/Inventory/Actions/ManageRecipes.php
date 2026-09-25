<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\PriceResolver;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\ModifierRecipeItem;
use App\Modules\Inventory\Models\RecipeItem;
use App\Modules\Inventory\Support\RecipeCalculator;
use App\Modules\Inventory\Support\Units;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A product's recipe per size, the effects of its modifiers, and the resulting cost, price,
 * margin and food-cost share per size (at current average ingredient costs).
 */
final class ManageRecipes
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, mixed> */
    public function show(Product $product, ?string $branchId = null): array
    {
        $product->loadMissing(['variants' => fn ($q) => $q->orderBy('sort'), 'variants.prices', 'modifierGroups.modifiers']);
        $modifiers = $product->modifierGroups->flatMap(fn (ModifierGroup $g) => $g->modifiers->map(fn (Modifier $m) => ['group' => $g->name, 'modifier' => $m]));
        $recipes = RecipeCalculator::load($product->variants->pluck('id')->all(), $modifiers->map(fn (array $m) => $m['modifier']->id)->values()->all());
        $ingredients = $recipes['ingredients'];

        $line = fn (string $ingredientId, float $qty): array => [
            'ingredient_id' => $ingredientId,
            'name' => $ingredients[$ingredientId]->name ?? '',
            'unit' => $ingredients[$ingredientId]->unit->value ?? 'g',
            'quantity' => $qty,
            'cost' => isset($ingredients[$ingredientId]) ? $ingredients[$ingredientId]->costOf(abs($qty)) * ($qty < 0 ? -1 : 1) : 0,
        ];

        return [
            'variants' => $product->variants->map(function (ProductVariant $v) use ($recipes, $ingredients, $line, $branchId): array {
                $items = $recipes['variants'][$v->id] ?? [];
                $cost = RecipeCalculator::cost($items, $ingredients);
                $price = PriceResolver::amountFor($v, $branchId) ?? 0;

                return [
                    'variant_id' => $v->id,
                    'name' => $v->name,
                    'price' => $price,
                    'cost' => $cost,
                    'margin' => $price - $cost,
                    'food_cost_ratio' => $price > 0 ? round($cost / $price, 4) : null,
                    'items' => array_map($line, array_keys($items), array_values($items)),
                ];
            })->values()->all(),
            'modifiers' => $modifiers->map(fn (array $m) => [
                'modifier_id' => $m['modifier']->id,
                'group' => $m['group'],
                'name' => $m['modifier']->name,
                'price_delta' => $m['modifier']->price_delta,
                'items' => array_map($line, array_keys($recipes['modifiers'][$m['modifier']->id] ?? []), array_values($recipes['modifiers'][$m['modifier']->id] ?? [])),
            ])->values()->all(),
        ];
    }

    /**
     * Replaces the recipe of the given variants and modifiers (only those that belong to the product).
     *
     * @param  list<array{variant_id: string, items: list<array{ingredient_id: string, quantity: float, unit?: ?string}>}>  $variants
     * @param  list<array{modifier_id: string, items: list<array{ingredient_id: string, quantity: float, unit?: ?string}>}>  $modifiers
     */
    public function save(Product $product, array $variants, array $modifiers): void
    {
        $product->loadMissing(['variants', 'modifierGroups.modifiers']);
        $ownVariants = $product->variants->pluck('id')->flip();
        $ownModifiers = $product->modifierGroups->flatMap(fn (ModifierGroup $g) => $g->modifiers->pluck('id'))->flip();
        $ingredients = Ingredient::query()->whereIn('id', [
            ...collect($variants)->flatMap(fn ($v) => array_column($v['items'], 'ingredient_id')),
            ...collect($modifiers)->flatMap(fn ($m) => array_column($m['items'], 'ingredient_id')),
        ])->get()->keyBy('id');

        DB::transaction(function () use ($variants, $modifiers, $ownVariants, $ownModifiers, $ingredients): void {
            foreach ($variants as $v) {
                if (! $ownVariants->has($v['variant_id'])) {
                    continue;
                }
                RecipeItem::query()->where('variant_id', $v['variant_id'])->delete();
                foreach ($this->merge($v['items'], $ingredients, allowNegative: false) as $ingredientId => $qty) {
                    RecipeItem::query()->create(['variant_id' => $v['variant_id'], 'ingredient_id' => $ingredientId, 'quantity' => $qty]);
                }
            }

            foreach ($modifiers as $m) {
                if (! $ownModifiers->has($m['modifier_id'])) {
                    continue;
                }
                ModifierRecipeItem::query()->where('modifier_id', $m['modifier_id'])->delete();
                foreach ($this->merge($m['items'], $ingredients, allowNegative: true) as $ingredientId => $qty) {
                    ModifierRecipeItem::query()->create(['modifier_id' => $m['modifier_id'], 'ingredient_id' => $ingredientId, 'quantity' => $qty]);
                }
            }
        });

        $this->audit->record('product.recipe_updated', $product, ['variants' => count($variants), 'modifiers' => count($modifiers)]);
    }

    /**
     * @param  list<array{ingredient_id: string, quantity: float, unit?: ?string}>  $items
     * @param  Collection<string, Ingredient>  $ingredients
     * @return array<string, float>
     */
    private function merge(array $items, $ingredients, bool $allowNegative): array
    {
        $out = [];
        foreach ($items as $item) {
            $ingredient = $ingredients->get($item['ingredient_id']);
            if ($ingredient === null) {
                continue;
            }
            $qty = Units::toBase($ingredient, (float) $item['quantity'], $item['unit'] ?? null);
            $out[$ingredient->id] = round(($out[$ingredient->id] ?? 0) + ($allowNegative ? $qty : abs($qty)), 3);
        }

        return array_filter($out, fn (float $q) => abs($q) >= 0.001);
    }
}
