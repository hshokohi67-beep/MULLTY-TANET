<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\Slugger;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a product's details and category membership.
 * When $variants is given (always on create), variants and base prices are synced too.
 */
final class SaveProduct
{
    public function __construct(
        private readonly SyncVariants $syncVariants,
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<VariantData>|null  $variants
     */
    public function handle(ProductData $data, ?Product $product = null, ?array $variants = null, ?string $actorId = null): Product
    {
        return DB::transaction(function () use ($data, $product, $variants, $actorId): Product {
            $product ??= new Product;
            $isNew = ! $product->exists;

            $product->fill([
                'name' => $data->name,
                'description' => $data->description,
                'is_active' => $data->isActive,
                'is_featured' => $data->isFeatured,
                'sort' => $data->sort,
                'nutrition' => $data->nutrition,
                'dietary_tags' => $data->dietaryTags,
                'temperature' => $data->temperature,
            ]);

            if ($isNew) {
                $product->slug = Slugger::unique($data->name, fn (string $s) => Product::withTrashed()->where('slug', $s)->exists());
            }

            $changes = $product->getDirty();
            $product->save();

            $tenantId = $this->context->require()->getKey();
            $product->categories()->sync(collect($data->categoryIds)->mapWithKeys(fn (string $id, int $i) => [$id => ['tenant_id' => $tenantId, 'sort' => $i]])->all());

            if ($variants !== null) {
                $this->syncVariants->handle($product, $variants, $actorId);
            }

            unset($changes['search_text']);
            $this->audit->record($isNew ? 'product.created' : 'product.updated', $product, $changes);

            return $product;
        });
    }
}
