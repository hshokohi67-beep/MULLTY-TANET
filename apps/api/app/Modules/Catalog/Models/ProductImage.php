<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $product_id
 * @property string $path
 * @property ?string $alt
 * @property ?int $width
 * @property ?int $height
 * @property int $sort
 */
#[Fillable(['product_id', 'path', 'alt', 'width', 'height', 'sort'])]
class ProductImage extends Model
{
    use BelongsToTenant, HasUlids;

    public const MAX_PER_PRODUCT = 6;

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }
}
