<?php

namespace App\Modules\Advertising\Support;

use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Models\AdSlot;
use App\Modules\Marketplace\Contracts\SponsoredContent;
use App\Modules\Marketplace\Models\MarketplaceStore;
use Illuminate\Support\Collection;

/**
 * Serves live campaigns from the public `ad_slots` projection. A slot is shown only while its café
 * is in the marketplace projection (hidden, unlisted or ineligible cafés disappear at once).
 * The order rotates every few minutes so every paid campaign gets its turn.
 */
final class AdServing implements SponsoredContent
{
    private const MAX_BANNERS = 6;

    private const ROTATE_SECONDS = 300;

    public function banners(?string $city): array
    {
        $slots = AdSlot::query()->live(AdPlacement::HOME_BANNER)->forCity($city)->get();
        if ($slots->isEmpty()) {
            return [];
        }
        $stores = $this->stores($slots->pluck('store_slug')->unique()->values()->all());

        return $this->rotate($slots->filter(fn (AdSlot $s) => isset($stores[$s->store_slug])))
            ->take(self::MAX_BANNERS)
            ->map(fn (AdSlot $s) => AdPresenter::banner($s, $stores[$s->store_slug]))
            ->values()->all();
    }

    public function sponsored(array $storeSlugs, ?string $city, int $limit): array
    {
        if ($storeSlugs === [] || $limit < 1) {
            return [];
        }
        $matching = array_flip($storeSlugs);
        $slots = AdSlot::query()->live(AdPlacement::SEARCH_TOP)->forCity($city)->get()
            ->filter(fn (AdSlot $s) => isset($matching[$s->store_slug]))
            ->unique('store_slug');

        return $this->rotate($slots)->take($limit)
            ->map(fn (AdSlot $s) => ['store' => $s->store_slug, 'ad' => AdPresenter::sponsored($s)])
            ->values()->all();
    }

    /**
     * The most popular listed branch of each café.
     *
     * @param  list<string>  $slugs
     * @return array<string, MarketplaceStore>
     */
    private function stores(array $slugs): array
    {
        $out = [];
        foreach (MarketplaceStore::query()->whereIn('store_slug', $slugs)->orderByDesc('popularity')->get() as $row) {
            $out[$row->store_slug] ??= $row;
        }

        return $out;
    }

    /**
     * @param  Collection<int, AdSlot>  $slots
     * @return Collection<int, AdSlot>
     */
    private function rotate(Collection $slots): Collection
    {
        $turn = intdiv(now()->getTimestamp(), self::ROTATE_SECONDS);

        return $slots->sortBy(fn (AdSlot $s) => crc32($s->ref.$turn))->values();
    }
}
