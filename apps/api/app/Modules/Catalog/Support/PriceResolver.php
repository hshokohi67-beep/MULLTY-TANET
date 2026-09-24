<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\ProductVariant;

/**
 * The one rule for "what does this variant cost at this branch": the branch override
 * if one exists, otherwise the base price. Expects `prices` to be eager-loaded.
 */
final class PriceResolver
{
    public static function amountFor(ProductVariant $variant, ?string $branchId): ?int
    {
        $prices = $variant->prices;

        if ($branchId !== null) {
            $override = $prices->firstWhere('branch_id', $branchId);

            if ($override !== null) {
                return $override->amount;
            }
        }

        return $prices->firstWhere('branch_id', null)?->amount;
    }
}
