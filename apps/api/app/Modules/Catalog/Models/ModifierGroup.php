<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reusable choice group, e.g. «نوع شیر» (milk type) min 1 / max 1, or «افزودنی‌ها» (add-ons) min 0 / unlimited.
 *
 * @property string $id
 * @property string $name
 * @property int $min_select
 * @property int $max_select 0 = unlimited
 * @property int $sort
 */
#[Fillable(['name', 'min_select', 'max_select', 'sort'])]
class ModifierGroup extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['min_select' => 'integer', 'max_select' => 'integer', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }

    /** @return HasMany<Modifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)->orderBy('sort');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modifier_groups')->withPivot(['sort', 'tenant_id']);
    }

    public function isRequired(): bool
    {
        return $this->min_select > 0;
    }
}
