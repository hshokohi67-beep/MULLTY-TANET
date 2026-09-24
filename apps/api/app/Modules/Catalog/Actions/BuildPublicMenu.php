<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\AvailabilityStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogVersion;
use App\Modules\Catalog\Support\PriceResolver;
use App\Modules\Core\Models\Branch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The public menu for one branch: an explicit allow-list projection, prices resolved for
 * the branch, hidden/inactive items removed. Cached until the next catalog change
 * (sold-out expiry is re-evaluated at most every minute).
 */
final class BuildPublicMenu
{
    /** @return array<string, mixed> */
    public function handle(Branch $branch): array
    {
        $key = sprintf('public-menu:%s:%s:%s', $branch->tenant_id, $branch->getKey(), CatalogVersion::current($branch->tenant_id));

        return Cache::remember($key, now()->addMinute(), fn () => $this->build($branch));
    }

    /** @return array<string, mixed> */
    private function build(Branch $branch): array
    {
        $disk = Storage::disk(config('filesystems.media_disk'));

        $products = Product::query()
            ->where('is_active', true)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true)->with('prices'),
                'images',
                'categories:id,temperature',
                'availability' => fn ($q) => $q->where('branch_id', $branch->getKey()),
                'modifierGroups.modifiers' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('sort')->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($branch, $disk): ?array {
                $availability = $product->availability->first();
                $status = $availability?->effectiveStatus() ?? AvailabilityStatus::Available;

                if ($status === AvailabilityStatus::Hidden) {
                    return null;
                }

                $variants = $product->variants
                    ->map(fn (ProductVariant $v) => ['id' => $v->id, 'name' => $v->name, 'price' => PriceResolver::amountFor($v, $branch->getKey())])
                    ->filter(fn (array $v) => $v['price'] !== null)
                    ->values();

                if ($variants->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'description' => $product->description,
                    'is_featured' => $product->is_featured,
                    // Menu mood: the product's own value, else its first category's.
                    'temperature' => $product->temperature ?? $product->categories->pluck('temperature')->filter()->first(),
                    'is_available' => $status === AvailabilityStatus::Available,
                    'category_ids' => $product->categories->modelKeys(),
                    'price_from' => $variants->min('price'),
                    'variants' => $variants->all(),
                    'images' => $product->images->map(fn (ProductImage $i) => ['url' => $disk->url($i->path), 'alt' => $i->alt, 'width' => $i->width, 'height' => $i->height])->all(),
                    'nutrition' => $product->nutrition,
                    'dietary_tags' => collect($product->dietary_tags ?? [])->map(fn (string $t) => ['key' => $t, 'label' => Product::DIETARY_TAGS[$t] ?? $t])->values()->all(),
                    'modifier_groups' => $product->modifierGroups->map(fn (ModifierGroup $g) => [
                        'id' => $g->id,
                        'name' => $g->name,
                        'min_select' => $g->min_select,
                        'max_select' => $g->max_select,
                        'modifiers' => $g->modifiers->map(fn (Modifier $m) => ['id' => $m->id, 'name' => $m->name, 'price_delta' => $m->price_delta, 'is_default' => $m->is_default])->all(),
                    ])->all(),
                ];
            })
            ->filter()
            ->values();

        $categories = Category::query()->where('is_active', true)->orderBy('sort')->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'description', 'temperature', 'image_path'])
            ->map(fn (Category $c) => [
                ...$c->only(['id', 'parent_id', 'name', 'slug', 'description', 'temperature']),
                'image_url' => $c->image_path ? $disk->url($c->image_path) : null,
            ])
            ->values();

        return [
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'slug' => $branch->slug],
            'categories' => $categories->all(),
            'products' => $products->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
