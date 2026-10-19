<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\MenuInsights;
use App\Modules\Catalog\Contracts\MenuOfferFeed;
use App\Modules\Catalog\Enums\AvailabilityStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\MenuCollection;
use App\Modules\Catalog\Models\MenuTag;
use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogVersion;
use App\Modules\Catalog\Support\PriceResolver;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Support\TenantSettings;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
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

        $menu = Cache::remember($key, now()->addMinute(), fn () => $this->build($branch));
        $menu['deals'] = $this->deals($branch, $menu['products']);

        return $menu;
    }

    /**
     * Offers and today's specials, rebuilt on every call (they follow the clock and the discounts,
     * not the catalog version). Windows are UTC instants; the web decides what is on at a moment.
     *
     * @param  list<array<string, mixed>>  $products
     * @return array{offers: list<array<string, mixed>>, specials: list<array{product_id: string, windows: list<array{from: ?string, to: ?string}>}>}
     */
    private function deals(Branch $branch, array $products): array
    {
        $now = CarbonImmutable::now(app(TenantContext::class)->require()->timezone);
        $available = collect($products)->where('is_available', true);

        $offers = [];
        foreach (app(MenuOfferFeed::class)->forBranch($branch->getKey(), $now) as $offer) {
            $ids = $available->filter(fn (array $p) => in_array($p['id'], $offer['product_ids'], true) || array_intersect($p['category_ids'], $offer['category_ids']) !== [])
                ->pluck('id')->values()->all();
            if ($ids !== []) {
                $offers[] = ['id' => $offer['id'], 'label' => $offer['label'], 'kind' => $offer['kind'], 'value' => $offer['value'], 'priority' => $offer['priority'], 'product_ids' => $ids, 'windows' => $offer['windows']];
            }
        }

        $specials = [];
        foreach ($available as $p) {
            $days = $p['special_days'] ?? [];
            if ($days !== []) {
                $specials[] = ['product_id' => $p['id'], 'windows' => self::dayWindows($days, $now)];
            }
        }

        return ['offers' => $offers, 'specials' => array_values(array_filter($specials, fn (array $s) => $s['windows'] !== []))];
    }

    /**
     * Whole days (in the café's timezone) on the given ISO weekdays, from today for a few days,
     * merged when consecutive. Every day of the week = open-ended.
     *
     * @param  list<int>  $weekdays
     * @return list<array{from: ?string, to: ?string}>
     */
    public static function dayWindows(array $weekdays, CarbonImmutable $now, int $days = 3): array
    {
        if (count(array_unique($weekdays)) >= 7) {
            return [['from' => null, 'to' => null]];
        }

        $windows = [];
        for ($offset = 0; $offset <= $days; $offset++) {
            $day = $now->startOfDay()->addDays($offset);
            if (! in_array($day->isoWeekday(), $weekdays, true)) {
                continue;
            }
            $last = array_key_last($windows);
            if ($last !== null && $windows[$last][1]->equalTo($day)) {
                $windows[$last][1] = $day->addDay();
            } else {
                $windows[] = [$day, $day->addDay()];
            }
        }

        return array_map(fn (array $w) => ['from' => $w[0]->utc()->toIso8601String(), 'to' => $w[1]->utc()->toIso8601String()], $windows);
    }

    /** @return array<string, mixed> */
    private function build(Branch $branch): array
    {
        $disk = Storage::disk(config('filesystems.media_disk'));
        // A product counts as «جدید» for a few weeks, but not the whole menu a café set up (or imported) in its first week.
        $setupUntil = CarbonImmutable::parse((string) app(TenantContext::class)->require()->created_at)->addDays(7);
        $newSince = now()->subDays(Product::NEW_FOR_DAYS);
        $tagLabels = [...Product::DIETARY_TAGS, ...MenuTag::query()->get()->mapWithKeys(fn (MenuTag $t) => [$t->key() => $t->label])->all()];

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
            ->map(function (Product $product) use ($branch, $disk, $tagLabels, $setupUntil, $newSince): ?array {
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
                    'name_en' => $product->name_en,
                    'description' => $product->description,
                    'ingredients' => $product->ingredients,
                    'is_featured' => $product->is_featured,
                    'is_new' => $product->is_new || ($product->created_at !== null && $product->created_at->greaterThan($newSince) && $product->created_at->greaterThan($setupUntil)),
                    'special_days' => $product->special_days ?? [],
                    // Menu mood: the product's own value, else its first category's.
                    'temperature' => $product->temperature ?? $product->categories->pluck('temperature')->filter()->first(),
                    'is_available' => $status === AvailabilityStatus::Available,
                    'category_ids' => $product->categories->modelKeys(),
                    'price_from' => $variants->min('price'),
                    'variants' => $variants->all(),
                    'images' => $product->images->map(fn (ProductImage $i) => ['url' => $disk->url($i->path), 'alt' => $i->alt, 'width' => $i->width, 'height' => $i->height])->all(),
                    'nutrition' => $product->nutrition,
                    'allergens' => collect($product->allergens ?? [])->filter(fn (string $a) => isset(Product::ALLERGENS[$a]))->map(fn (string $a) => ['key' => $a, 'label' => Product::ALLERGENS[$a]])->values()->all(),
                    'caffeine' => $product->caffeine_source || $product->caffeine_dose ? ['source' => Product::CAFFEINE_SOURCES[$product->caffeine_source] ?? null, 'dose' => $product->caffeine_dose] : null,
                    'dietary_tags' => collect($product->dietary_tags ?? [])->filter(fn (string $t) => isset($tagLabels[$t]))->map(fn (string $t) => ['key' => $t, 'label' => $tagLabels[$t]])->values()->all(),
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
            'insights' => $this->insights($products->pluck('id')->all()),
            'collections' => $this->collections($products->pluck('id')->all()),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Active «مجموعه‌ی منو» highlights with their time window (the web shows those on now) and theme.
     *
     * @param  list<string>  $onMenu
     * @return list<array{id: string, title: string, subtitle: ?string, theme: ?string, product_ids: list<string>, window: array{from: ?string, to: ?string}}>
     */
    private function collections(array $onMenu): array
    {
        $on = array_flip($onMenu);

        return MenuCollection::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->with('products:id')->orderBy('starts_at')->get()
            ->map(fn (MenuCollection $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'subtitle' => $c->subtitle,
                'theme' => $c->theme,
                'product_ids' => array_values(array_filter($c->products->modelKeys(), fn (string $id) => isset($on[$id]))),
                'window' => ['from' => $c->starts_at?->utc()->toIso8601String(), 'to' => $c->ends_at?->utc()->toIso8601String()],
            ])
            ->filter(fn (array $c) => $c['product_ids'] !== [])
            ->values()->all();
    }

    /**
     * Best sellers and "goes well with" pairs, limited to products on this menu and switched off
     * when the café hides them.
     *
     * @param  list<string>  $onMenu
     * @return array{popular: list<string>, pairs: array<string, list<string>>}
     */
    private function insights(array $onMenu): array
    {
        $insights = app(MenuInsights::class);
        $on = array_flip($onMenu);
        $popular = TenantSettings::get('storefront.show_popular')
            ? array_values(array_filter($insights->popular(), fn (string $id) => isset($on[$id])))
            : [];
        $pairs = [];
        if (TenantSettings::get('storefront.suggestions')) {
            foreach ($insights->pairs() as $id => $with) {
                $with = array_values(array_filter($with, fn (string $other) => isset($on[$other])));
                if (isset($on[$id]) && $with !== []) {
                    $pairs[$id] = array_slice($with, 0, 3);
                }
            }
        }

        return ['popular' => $popular, 'pairs' => $pairs];
    }
}
