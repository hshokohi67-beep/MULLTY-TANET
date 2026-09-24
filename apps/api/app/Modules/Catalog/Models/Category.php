<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property ?string $parent_id
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property int $sort
 * @property bool $is_active
 * @property ?string $temperature hot|cold|null
 * @property ?string $image_path
 */
#[Fillable(['parent_id', 'name', 'slug', 'description', 'sort', 'is_active', 'temperature'])]
class Category extends Model
{
    use BelongsToTenant, HasUlids;

    public const MAX_DEPTH = 3;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories')->withPivot(['sort', 'tenant_id']);
    }

    /** Depth of this category (root = 1). */
    public function depth(): int
    {
        $depth = 1;
        $parentId = $this->parent_id;

        while ($parentId !== null && $depth <= self::MAX_DEPTH + 1) {
            $depth++;
            $parentId = self::query()->whereKey($parentId)->value('parent_id');
        }

        return $depth;
    }

    /**
     * This category's id plus every descendant id.
     *
     * @return list<string>
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        for ($level = 0; $level < self::MAX_DEPTH && $frontier !== []; $level++) {
            $frontier = self::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
