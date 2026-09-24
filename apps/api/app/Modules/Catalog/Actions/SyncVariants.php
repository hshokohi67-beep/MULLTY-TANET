<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Enums\PriceChangeReason;
use App\Modules\Catalog\Exceptions\CatalogRuleException;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a product's variant list: updates listed ones, creates new ones, removes the rest.
 * A product with a single unnamed variant is a "simple" product.
 */
final class SyncVariants
{
    public function __construct(private readonly SetVariantPrice $setPrice) {}

    /**
     * @param  list<VariantData>  $variants
     */
    public function handle(Product $product, array $variants, ?string $actorId = null, PriceChangeReason $reason = PriceChangeReason::Manual): void
    {
        if (! collect($variants)->contains(fn (VariantData $v) => $v->isActive)) {
            throw CatalogRuleException::atLeastOneVariant();
        }

        DB::transaction(function () use ($product, $variants, $actorId, $reason): void {
            $keep = [];

            foreach ($variants as $index => $data) {
                $variant = $data->id !== null
                    ? ProductVariant::query()->where('product_id', $product->getKey())->findOrFail($data->id)
                    : new ProductVariant(['product_id' => $product->getKey()]);

                $variant->fill([
                    'name' => count($variants) === 1 ? ($data->name ?: null) : $data->name,
                    'sku' => $data->sku,
                    'is_active' => $data->isActive,
                    'is_default' => $index === 0,
                    'sort' => $index,
                ]);
                $variant->save();

                $this->setPrice->handle($variant, null, $data->basePrice, $reason, $actorId);
                $keep[] = $variant->getKey();
            }

            ProductVariant::query()->where('product_id', $product->getKey())->whereNotIn('id', $keep)->delete();
        });
    }
}
