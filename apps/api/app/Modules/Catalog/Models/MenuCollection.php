<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A time-boxed highlight on the menu, e.g. «طعم‌های پاییزی» or «ویژه‌ی یلدا», with an optional
 * seasonal theme for the shop pages. Ends by itself at `ends_at`.
 *
 * @property string $id
 * @property string $title
 * @property ?string $subtitle
 * @property ?string $theme
 * @property ?Carbon $starts_at
 * @property ?Carbon $ends_at
 * @property ?string $occasion_key
 * @property bool $is_active
 * @property Collection<int, Product> $products
 */
#[Fillable(['title', 'subtitle', 'theme', 'starts_at', 'ends_at', 'occasion_key', 'is_active'])]
class MenuCollection extends Model
{
    use BelongsToTenant, HasUlids;

    public const THEMES = ['nowruz', 'yalda', 'autumn', 'winter', 'summer', 'ramadan'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'menu_collection_products')->withPivot(['sort', 'tenant_id'])->orderByPivot('sort');
    }
}
