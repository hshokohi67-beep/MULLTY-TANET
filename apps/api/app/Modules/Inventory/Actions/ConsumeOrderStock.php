<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderItemModifier;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\OrderItemCost;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\RecipeCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Sale-time stock: when an order is placed, every item's recipe (variant + modifiers, × quantity)
 * leaves the branch's stock and the item's cost is frozen at current average costs. Cancelling or
 * rejecting puts the stock back. Both directions are idempotent per item and ingredient.
 */
final class ConsumeOrderStock
{
    public function __construct(private readonly PostStockMovement $stock) {}

    public function consume(Order $order): void
    {
        $order->loadMissing('items.modifiers');
        $modifierIds = fn (OrderItem $i): array => $i->modifiers->map(fn (OrderItemModifier $m) => $m->modifier_id)->filter()->values()->all();
        $recipes = RecipeCalculator::load(
            $order->items->pluck('variant_id')->filter()->values()->all(),
            $order->items->flatMap($modifierIds)->unique()->values()->all(),
        );

        if ($recipes['variants'] === [] && $recipes['modifiers'] === []) {
            return; // no recipes yet: nothing to track
        }

        DB::transaction(function () use ($order, $recipes, $modifierIds): void {
            foreach ($order->items as $item) {
                $breakdown = [];

                foreach (RecipeCalculator::usage($recipes, $item->variant_id, $modifierIds($item)) as $ingredientId => $qty) {
                    $total = round($qty * $item->quantity, 3);
                    $ingredient = $recipes['ingredients'][$ingredientId] ?? null;
                    $this->stock->handle($ingredientId, $order->branch_id, StockMovementType::Sale, -$total, "sale:{$item->id}:{$ingredientId}", [
                        'unit_cost' => $ingredient?->avg_cost,
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                    ]);
                    $breakdown[] = ['ingredient_id' => $ingredientId, 'name' => $ingredient->name ?? '', 'quantity' => $total, 'cost' => $ingredient?->costOf($total) ?? 0];
                }

                if ($breakdown !== []) {
                    OrderItemCost::query()->firstOrCreate(['order_item_id' => $item->id], [
                        'order_id' => $order->id,
                        'cost' => array_sum(array_column($breakdown, 'cost')),
                        'breakdown' => $breakdown,
                    ]);
                }
            }
        });
    }

    /** Puts back exactly what the sale took (read from the ledger, so later recipe edits don't matter). */
    public function restore(Order $order): void
    {
        $sales = StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::Sale)->get();

        DB::transaction(function () use ($sales, $order): void {
            foreach ($sales as $sale) {
                $this->stock->handle($sale->ingredient_id, $sale->branch_id, StockMovementType::SaleReversal, -(float) $sale->quantity, "reversal:{$sale->id}", [
                    'unit_cost' => $sale->unit_cost,
                    'order_id' => $order->id,
                    'order_item_id' => $sale->order_item_id,
                    'note' => 'لغو سفارش',
                ]);
            }
        });
    }
}
