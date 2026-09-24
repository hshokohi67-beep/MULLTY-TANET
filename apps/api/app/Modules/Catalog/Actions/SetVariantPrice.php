<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\PriceChangeReason;
use App\Modules\Catalog\Exceptions\InvalidPriceException;
use App\Modules\Catalog\Models\PriceChangeLog;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * The only way prices change. Every change (including removing a branch override)
 * writes a PriceChangeLog row, so price history is always complete.
 */
final class SetVariantPrice
{
    /**
     * @param  ?int  $amount  rial; null removes a branch override (not allowed for the base price)
     * @return bool whether anything changed
     */
    public function handle(
        ProductVariant $variant,
        ?string $branchId,
        ?int $amount,
        PriceChangeReason $reason,
        ?string $actorId = null,
        ?string $batchId = null,
    ): bool {
        if ($amount !== null && $amount < 0) {
            throw InvalidPriceException::negative();
        }

        if ($branchId === null && $amount === null) {
            throw InvalidPriceException::baseRequired();
        }

        $price = ProductPrice::query()
            ->where('variant_id', $variant->getKey())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId), fn ($q) => $q->whereNull('branch_id'))
            ->lockForUpdate()
            ->first();

        $old = $price?->amount;

        if ($old === $amount) {
            return false;
        }

        if ($amount === null) {
            $price?->delete();
        } elseif ($price !== null) {
            $price->update(['amount' => $amount]);
        } else {
            ProductPrice::query()->create(['variant_id' => $variant->getKey(), 'branch_id' => $branchId, 'amount' => $amount]);
        }

        PriceChangeLog::query()->create([
            'variant_id' => $variant->getKey(),
            'branch_id' => $branchId,
            'old_amount' => $old,
            'new_amount' => $amount,
            'reason' => $reason,
            'batch_id' => $batchId,
            'actor_id' => $actorId,
        ]);

        return true;
    }
}
