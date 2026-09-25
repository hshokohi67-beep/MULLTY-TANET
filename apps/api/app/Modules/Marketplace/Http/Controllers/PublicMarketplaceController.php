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

        return $this->cached([
            'featured' => $stores->filter(fn (MarketplaceStore $s) => $s->isFeatured())->sortByDesc('popularity')->take(8)->map(fn ($s) => StorePresenter::card($s))->values()->all(),
            'popular' => $stores->sortByDesc('popularity')->take(8)->map(fn ($s) => StorePresenter::card($s))->values()->all(),
            'newest' => $stores->sortByDesc(fn (MarketplaceStore $s) => $s->listed_at?->getTimestamp() ?? 0)->take(8)->map(fn ($s) => StorePresenter::card($s))->values()->all(),
            'cities' => $cities,
            'categories' => $categories,
            'amenities' => MarketplaceCatalog::labelled(array_keys(MarketplaceCatalog::AMENITIES), MarketplaceCatalog::AMENITIES),
            'total' => $stores->count(),
        ]);
    }

    public function stores(Request $request): JsonResponse
    {
        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', Rule::in(array_keys(MarketplaceCatalog::CATEGORIES))],
            'amenities' => ['nullable', 'array', 'max:5'],
            'amenities.*' => [Rule::in(array_keys(MarketplaceCatalog::AMENITIES))],
            'price' => ['nullable', 'integer', 'between:1,4'],
            'open_now' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['relevance', 'nearest', 'newest'])],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $like = fn (string $s) => '%'.addcslashes($s, '%_\\').'%';
        $query = MarketplaceStore::query()
            ->when($v['city'] ?? null, fn ($q, $city) => $q->where('city', trim($city)))
            ->when($v['category'] ?? null, fn ($q, $c) => $q->where('category_keys', 'like', $like("|{$c}|")))
            ->when($v['price'] ?? null, fn ($q, $p) => $q->where('price_level', (int) $p));
        foreach ($v['amenities'] ?? [] as $amenity) {
            $query->where('amenity_keys', 'like', $like("|{$amenity}|"));
        }
        $terms = SearchText::terms((string) ($v['q'] ?? ''));
        foreach ($terms as $term) {
            $query->where('search_text', 'like', $like($term));
        }

        $lat = isset($v['lat']) ? (float) $v['lat'] : null;
        $lng = isset($v['lng']) ? (float) $v['lng'] : null;
        $rows = $query->limit(2000)->get()->map(fn (MarketplaceStore $s) => [
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
                default => [(int) $b['name_hit'], $b['row']->popularity] <=> [(int) $a['name_hit'], $a['row']->popularity],
            };
        })->values();

        $page = (int) ($v['page'] ?? 1);
        $total = $rows->count();

        return $this->cached([
            'data' => $rows->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->map(fn (array $r) => StorePresenter::card($r['row'], $r['distance']))->values()->all(),
            'meta' => ['page' => $page, 'per_page' => self::PER_PAGE, 'total' => $total, 'last_page' => max(1, (int) ceil($total / self::PER_PAGE))],
        ], wrap: false);
    }

    public function show(string $storeSlug): JsonResponse
    {
        $rows = MarketplaceStore::query()->where('store_slug', mb_strtolower($storeSlug))->orderBy('created_at')->get();
        if ($rows->isEmpty()) {
            throw new NotFoundHttpException;
        }

        return $this->cached(StorePresenter::profile($rows->toBase()));
    }

    /** @param  array<string, mixed>  $data */
    private function cached(array $data, bool $wrap = true): JsonResponse
    {
        return response()->json($wrap ? ['data' => $data] : $data)->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
