<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\IngredientStock;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY way stock changes. Locks the ingredient's stock row in that branch, appends a ledger
 * row with the resulting balance and updates the balance in one transaction, so
 * stock = Σ movements = last balance_after at all times.
 *
 * Stock may go negative (a sale is never blocked by the books; the panel flags it). With an
 * idempotency key, a repeated call returns the first movement instead of posting twice.
 */
final class PostStockMovement
{
    /**
     * @param  array{unit_cost?: ?int, order_id?: ?string, order_item_id?: ?string, purchase_order_id?: ?string, note?: ?string, actor_type?: string, actor_id?: ?string}  $context
     */
    public function handle(string $ingredientId, string $branchId, StockMovementType $type, float $quantity, ?string $idempotencyKey = null, array $context = []): StockMovement
    {
        if ($idempotencyKey !== null && ($existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($ingredientId, $branchId, $type, $quantity, $idempotencyKey, $context): StockMovement {
                IngredientStock::query()->firstOrCreate(['ingredient_id' => $ingredientId, 'branch_id' => $branchId], ['quantity' => 0]);
                /** @var IngredientStock $stock */
                $stock = IngredientStock::query()->where('ingredient_id', $ingredientId)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
                $balance = round((float) $stock->quantity + $quantity, 3);

                $movement = StockMovement::query()->create([
                    'ingredient_id' => $ingredientId,
                    'branch_id' => $branchId,
                    'type' => $type,
                    'quantity' => round($quantity, 3),
                    'balance_after' => $balance,
                    'unit_cost' => $context['unit_cost'] ?? null,
                    'order_id' => $context['order_id'] ?? null,
                    'order_item_id' => $context['order_item_id'] ?? null,
                    'purchase_order_id' => $context['purchase_order_id'] ?? null,
                    'note' => $context['note'] ?? null,
                    'actor_type' => $context['actor_type'] ?? 'system',
                    'actor_id' => $context['actor_id'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $stock->forceFill(['quantity' => $balance])->save();

                return $movement;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent call with the same key won.
            return $idempotencyKey !== null ? StockMovement::query()->where('idempotency_key', $idempotencyKey)->firstOrFail() : throw $e;
        }
    }
}
