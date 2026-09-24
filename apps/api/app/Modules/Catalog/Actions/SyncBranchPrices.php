<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\PriceChangeReason;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Branch;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Sets or clears one branch's price overrides for a product's variants.
 */
final class SyncBranchPrices
{
    public function __construct(
        private readonly SetVariantPrice $setPrice,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, ?int>  $amounts  variant_id => rial (null clears the override → base price applies)
     */
    public function handle(Product $product, Branch $branch, array $amounts, ?string $actorId): void
    {
        DB::transaction(function () use ($product, $branch, $amounts, $actorId): void {
            $variants = ProductVariant::query()->where('product_id', $product->getKey())->whereKey(array_keys($amounts))->get()->keyBy('id');
            $changed = [];

            foreach ($amounts as $variantId => $amount) {
                $variant = $variants->get($variantId);

                if ($variant !== null && $this->setPrice->handle($variant, $branch->getKey(), $amount, PriceChangeReason::Manual, $actorId)) {
                    $changed[$variantId] = $amount;
                }
            }

            if ($changed !== []) {
                $this->audit->record('product.branch_prices_updated', $product, ['branch_id' => $branch->getKey(), 'amounts' => $changed]);
            }
        });
    }
}
