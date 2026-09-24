<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Actions\SetVariantPrice;
use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A variant's price in integer rial. branch_id null = base price; otherwise a branch override.
 * Only write through {@see SetVariantPrice} so every change is logged.
 *
 * @property string $id
 * @property string $variant_id
 * @property ?string $branch_id
 * @property int $amount
 */
#[Fillable(['variant_id', 'branch_id', 'amount'])]
#[Hidden(['branch_scope'])]
class ProductPrice extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }
}
