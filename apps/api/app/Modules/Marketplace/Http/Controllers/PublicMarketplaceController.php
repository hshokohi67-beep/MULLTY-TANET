<?php

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Modules\Marketplace\Support\MarketplaceCatalog;
use App\Modules\Marketplace\Support\SearchText;
use App\Modules\Marketplace\Support\StorePresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public marketplace. Reads only the `marketplace_stores` projection (platform-level, no tenant
 * context) and answers through StorePresenter's allow-lists. Cacheable for a minute.
 */
final class PublicMarketplaceController
{
    private const PER_PAGE = 24;

    /** Curated rows on the home page: key => [title, subtitle, filter]. */
    private const COLLECTIONS = [
        'work' => ['برای کار و مطالعه', 'اینترنت، میز راحت و فضای آرام', ['amenities' => ['workspace']]],
        'offers' => ['تخفیف‌دارها', 'پیشنهادهای همین حالا', ['offers' => '1']],
        'late' => ['تا دیروقت باز', 'برای بعد از شام و شب‌نشینی', ['late' => '1']],
        'budget' => ['اقتصادی و خوب', 'خوش‌قیمت‌ها', ['price' => '1']],
        'breakfast' => ['صبحانه و برانچ', 'شروع خوب روز', ['category' => 'breakfast']],
        'outdoor' => ['فضای باز', 'میز زیر آسمان', ['amenities' => ['outdoor']]],
    ];

    public function home(): JsonResponse
    {
        $rows = MarketplaceStore::query()->orderByDesc('popularity')->get();
        // One card per café: its most popular branch.
        $first = [];
        foreach ($rows as $row) {
            $first[$row->store_slug] ??= $row;
        }
        $stores = collect($first);

        $categories = [];
        foreach (MarketplaceCatalog::CATEGORIES as $key => $label) {
            $count = $stores->filter(fn (MarketplaceStore $s) => in_array($key, $s->categories, true))->count();
            if ($count > 0) {
                $categories[] = ['key' => $key, 'label' => $label, 'count' => $count];
            }
        }
        $cities = $rows->groupBy('city')->map(fn (Collection $g, string $city) => ['city' => $city, 'count' => $g->pluck('store_slug')->unique()->count()])
            ->sortByDesc('count')->values()->take(24)->all();

        $collections = [];
        foreach (self::COLLECTIONS as $key => [$title, $subtitle, $filter]) {
            $matching = $stores->filter(fn (MarketplaceStore $s) => $this->matches($s, $filter))->take(8);
            if ($matching->count() >= 2) {
                $collections[] = ['key' => $key, 'title' => $title, 'subtitle' => $subtitle, 'filter' => $filter, 'stores' => $matching->map(fn ($s) => StorePresenter::card($s))->values()->all()];
            }
        }

        return $this->cached([
            'featured' => $stores->filter(fn (MarketplaceStore $s) => $s->isFeatured())->take(8)->map(fn ($s) => StorePresenter::card($s))->values()->all(),
            'popular' => $stores->take(8)->map(fn ($s) => StorePresenter::card($s))->values()->all(),
            'newest' => $stores->sortByDesc(fn (MarketplaceStore $s) => $s->listed_at?->getTimestamp() ?? 0)->take(8)->map(fn ($s) => StorePresenter::card($s))->values()->all(),
            'collections' => $collections,
            'places' => $this->places($rows),
            'cities' => $cities,
            'categories' => $categories,
            'amenities' => MarketplaceCatalog::labelled(array_keys(MarketplaceCatalog::AMENITIES), MarketplaceCatalog::AMENITIES),
            'dietary' => MarketplaceCatalog::labelled(array_keys(MarketplaceCatalog::DIETARY), MarketplaceCatalog::DIETARY),
            'total' => $stores->count(),
        ]);
    }

    public function stores(Request $request): JsonResponse
    {
        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'district' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', Rule::in(array_keys(MarketplaceCatalog::CATEGORIES))],
            'amenities' => ['nullable', 'array', 'max:5'],
            'amenities.*' => [Rule::in(array_keys(MarketplaceCatalog::AMENITIES))],
            'dietary' => ['nullable', 'array', 'max:3'],
            'dietary.*' => [Rule::in(array_keys(MarketplaceCatalog::DIETARY))],
            'stores' => ['nullable', 'array', 'max:50'],
            'stores.*' => ['string', 'regex:/^[a-z0-9-]{2,64}$/'],
            'price' => ['nullable', 'integer', 'between:1,4'],
            'open_now' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'offers' => ['nullable', 'boolean'],
            'delivery' => ['nullable', 'boolean'],
            'free_delivery' => ['nullable', 'boolean'],
            'online_payment' => ['nullable', 'boolean'],
            'preorder' => ['nullable', 'boolean'],
            'dine_in' => ['nullable', 'boolean'],
            'late' => ['nullable', 'boolean'],
            'new' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['relevance', 'nearest', 'newest', 'popular', 'price_asc', 'price_desc'])],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'page' => ['nullable', 'integer', 'between:1,100'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $like = fn (string $s) => '%'.addcslashes($s, '%_\\').'%';
        $query = MarketplaceStore::query()
            ->when($v['province'] ?? null, fn ($q, $x) => $q->where('province', trim($x)))
            ->when($v['city'] ?? null, fn ($q, $x) => $q->where('city', trim($x)))
            ->when($v['district'] ?? null, fn ($q, $x) => $q->where('district', trim($x)))
            ->when($v['category'] ?? null, fn ($q, $c) => $q->where('category_keys', 'like', $like("|{$c}|")))
            ->when($v['price'] ?? null, fn ($q, $p) => $q->where('price_level', (int) $p))
            ->when($v['stores'] ?? null, fn ($q, $slugs) => $q->whereIn('store_slug', $slugs))
            ->when($v['featured'] ?? false, fn ($q) => $q->where('featured_until', '>', now()))
            ->when($v['offers'] ?? false, fn ($q) => $q->where('has_offer', true))
            ->when($v['free_delivery'] ?? false, fn ($q) => $q->where('free_delivery', true))
            ->when($v['late'] ?? false, fn ($q) => $q->where('closes_late', true))
            ->when($v['new'] ?? false, fn ($q) => $q->where('listed_at', '>=', now()->subDays(30)));
        foreach ($v['amenities'] ?? [] as $amenity) {
            $query->where('amenity_keys', 'like', $like("|{$amenity}|"));
        }
        foreach ($v['dietary'] ?? [] as $diet) {
            $query->where('dietary_keys', 'like', $like("|{$diet}|"));
        }
        $terms = SearchText::terms((string) ($v['q'] ?? ''));
        foreach ($terms as $term) {
            $query->where('search_text', 'like', $like($term));
        }

        $lat = isset($v['lat']) ? (float) $v['lat'] : null;
        $lng = isset($v['lng']) ? (float) $v['lng'] : null;
        $services = array_filter(['delivery', 'online_payment', 'preorder', 'dine_in'], fn (string $k) => (bool) ($v[$k] ?? false));
        $rows = $query->limit(2000)->get()
            ->filter(fn (MarketplaceStore $s) => collect($services)->every(fn (string $k) => $s->services[$k] ?? false))
            ->map(fn (MarketplaceStore $s) => [
                'row' => $s,
                'distance' => $lat !== null && $lng !== null && $s->latitude !== null && $s->longitude !== null
                    ? StorePresenter::distance($lat, $lng, (float) $s->latitude, (float) $s->longitude) : null,
                // Names that match the query rank above matches elsewhere (menu items, amenities).
                'name_hit' => $terms !== [] && collect($terms)->every(fn (string $t) => str_contains(SearchText::normalize($s->name.' '.$s->branch_name), $t)),
            ]);

        if ($v['open_now'] ?? false) {
            $rows = $rows->filter(fn (array $r) => StorePresenter::openStatus($r['row'])['is_open']);
        }

        $sort = $v['sort'] ?? ($lat !== null ? 'nearest' : 'relevance');
        $rows = $rows->sort(function (array $a, array $b) use ($sort): int {
            // Featured always first (and labelled as such).
            $f = (int) $b['row']->isFeatured() <=> (int) $a['row']->isFeatured();
            if ($f !== 0) {
                return $f;
            }

            return match ($sort) {
                'nearest' => ($a['distance'] ?? INF) <=> ($b['distance'] ?? INF),
                'newest' => ($b['row']->listed_at?->getTimestamp() ?? 0) <=> ($a['row']->listed_at?->getTimestamp() ?? 0),
                'popular' => $b['row']->popularity <=> $a['row']->popularity,
                'price_asc' => [$a['row']->price_level ?? 9, -$a['row']->popularity] <=> [$b['row']->price_level ?? 9, -$b['row']->popularity],
                'price_desc' => [$b['row']->price_level ?? 0, $b['row']->popularity] <=> [$a['row']->price_level ?? 0, $a['row']->popularity],
                default => [(int) $b['name_hit'], $b['row']->popularity] <=> [(int) $a['name_hit'], $a['row']->popularity],
            };
        })->values();

        $per = (int) ($v['per_page'] ?? self::PER_PAGE);
        $page = (int) ($v['page'] ?? 1);
        $total = $rows->count();

        return $this->cached([
            'data' => $rows->slice(($page - 1) * $per, $per)->map(fn (array $r) => StorePresenter::card($r['row'], $r['distance']))->values()->all(),
            'meta' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $per))],
        ], wrap: false);
    }

    /** Type-ahead: cafés, places, categories and dishes matching what was typed so far. */
    public function suggest(Request $request): JsonResponse
    {
        $q = (string) $request->validate(['q' => ['required', 'string', 'max:40']])['q'];
        $needle = SearchText::normalize($q);
        if (mb_strlen($needle) < 2) {
            return $this->cached(['stores' => [], 'places' => [], 'categories' => [], 'dishes' => []]);
        }
        $rows = MarketplaceStore::query()->where('search_text', 'like', '%'.addcslashes($needle, '%_\\').'%')->orderByDesc('popularity')->limit(200)->get();
        $all = MarketplaceStore::query()->get(['city', 'district', 'province']);

        $stores = [];
        foreach ($rows as $r) {
            if (! isset($stores[$r->store_slug]) && str_contains(SearchText::normalize($r->name), $needle)) {
                $stores[$r->store_slug] = ['store' => $r->store_slug, 'name' => $r->name, 'city' => $r->city, 'logo_url' => $r->logo_url];
            }
        }
        $places = $all->flatMap(fn (MarketplaceStore $s) => array_filter([
            ['city' => $s->city, 'district' => null, 'label' => $s->city],
            $s->district ? ['city' => $s->city, 'district' => $s->district, 'label' => "{$s->district}، {$s->city}"] : null,
        ]))->unique('label')->filter(fn (array $p) => str_contains(SearchText::normalize($p['label']), $needle))->take(5)->values()->all();
        $categories = collect(MarketplaceCatalog::CATEGORIES)->filter(fn (string $label) => str_contains(SearchText::normalize($label), $needle))
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->take(5)->values()->all();
        $dishes = $rows->flatMap(fn (MarketplaceStore $s) => array_map(fn (array $h) => $h['name'], $s->highlights))
            ->filter(fn (string $name) => str_contains(SearchText::normalize($name), $needle))
            ->countBy()->sortDesc()->take(5)->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])->values()->all();

        return $this->cached(['stores' => array_slice(array_values($stores), 0, 5), 'places' => $places, 'categories' => $categories, 'dishes' => $dishes]);
    }

    public function show(string $storeSlug): JsonResponse
    {
        $rows = MarketplaceStore::query()->where('store_slug', mb_strtolower($storeSlug))->orderBy('created_at')->get();
        if ($rows->isEmpty()) {
            throw new NotFoundHttpException;
        }

        return $this->cached(StorePresenter::profile($rows->toBase()));
    }

    /**
     * Province → city → district, with café counts, only where cafés exist.
     *
     * @param  Collection<int, MarketplaceStore>  $rows
     * @return list<array{province: string, count: int, cities: list<array{city: string, count: int, districts: list<array{district: string, count: int}>}>}>
     */
    private function places(Collection $rows): array
    {
        $out = [];
        foreach ($rows->groupBy(fn (MarketplaceStore $s) => $s->province ?: $s->city) as $province => $inProvince) {
            $cities = [];
            foreach ($inProvince->groupBy('city') as $city => $inCity) {
                $districts = [];
                foreach ($inCity->filter(fn (MarketplaceStore $s) => $s->district !== null)->groupBy('district') as $district => $inDistrict) {
                    $districts[] = ['district' => (string) $district, 'count' => $inDistrict->pluck('store_slug')->unique()->count()];
                }
                usort($districts, fn (array $a, array $b) => $b['count'] <=> $a['count']);
                $cities[] = ['city' => (string) $city, 'count' => $inCity->pluck('store_slug')->unique()->count(), 'districts' => $districts];
            }
            usort($cities, fn (array $a, array $b) => $b['count'] <=> $a['count']);
            $out[] = ['province' => (string) $province, 'count' => $inProvince->pluck('store_slug')->unique()->count(), 'cities' => $cities];
        }
        usort($out, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    /** @param  array<string, mixed>  $filter */
    private function matches(MarketplaceStore $s, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            $ok = match ($key) {
                'amenities' => collect((array) $value)->every(fn ($a) => in_array($a, $s->amenities, true)),
                'category' => in_array($value, $s->categories, true),
                'offers' => $s->has_offer,
                'late' => $s->closes_late,
                'price' => $s->price_level === (int) $value,
                default => true,
            };
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $data */
    private function cached(array $data, bool $wrap = true): JsonResponse
    {
        return response()->json($wrap ? ['data' => $data] : $data)->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
