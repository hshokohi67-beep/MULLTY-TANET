<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Localization\PersianTextNormalizer;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort
 * @property ?array{calories?: int, caffeine_mg?: int, protein_g?: int} $nutrition
 * @property ?list<string> $dietary_tags
 * @property ?string $temperature hot|cold|null (null = inherit from the category)
 */
#[Fillable(['name', 'slug', 'description', 'is_active', 'is_featured', 'sort', 'nutrition', 'dietary_tags', 'temperature'])]
class Product extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    /** Diet/allergen tags the platform understands (labels are shown in the storefront). */
    public const DIETARY_TAGS = [
        'vegan' => 'گیاهی',
        'vegetarian' => 'گیاه‌خواری',
        'gluten_free' => 'بدون گلوتن',
        'dairy_free' => 'بدون لبنیات',
        'sugar_free' => 'بدون قند',
        'low_calorie' => 'کم‌کالری',
        'high_protein' => 'پرپروتئین',
        'spicy' => 'تند',
        'contains_nuts' => 'حاوی مغزها',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort' => 'integer',
            'nutrition' => 'array',
            'dietary_tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->search_text = PersianTextNormalizer::forSearch($product->name.' '.strip_tags((string) $product->description));
        });
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')->withPivot(['sort', 'tenant_id']);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort');
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    /** @return HasMany<ProductAvailability, $this> */
    public function availability(): HasMany
    {
        return $this->hasMany(ProductAvailability::class);
    }

    /** @return BelongsToMany<ModifierGroup, $this> */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_groups')->withPivot(['sort', 'tenant_id'])->orderByPivot('sort');
    }

    /**
     * Persian-aware search: "كافه" finds "کافه", "۲" finds "2", ZWNJ ≈ space.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $normalized = PersianTextNormalizer::forSearch($term);

        if ($normalized === '') {
            return;
        }

        foreach (explode(' ', $normalized) as $word) {
            $query->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
        }
    }
}
