<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Every product has at least one variant; prices always attach to variants.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property ?string $name
 * @property ?string $sku
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort
 * @property Product $product
 */
#[Fillable(['product_id', 'name', 'sku', 'is_default', 'is_active', 'sort'])]
class ProductVariant extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return HasMany<ProductPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'variant_id');
    }

    public function basePrice(): ?ProductPrice
    {
        return $this->prices->firstWhere('branch_id', null);
    }
}
