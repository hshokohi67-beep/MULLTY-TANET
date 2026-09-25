<?php

namespace App\Modules\Marketplace\Support;

use App\Modules\Core\Support\OpeningHoursEvaluator;
use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Support\Localization\Weekday;
use Illuminate\Support\Collection;

/**
 * The only way marketplace data leaves the API: explicit key lists over the public read model.
 * Never serialise a MarketplaceStore model directly (it carries tenant_id and popularity).
 */
final class StorePresenter
{
    /** @return array{is_open: bool, next_opening_at: ?string} */
    public static function openStatus(MarketplaceStore $s): array
    {
        if ($s->hours === []) {
            return ['is_open' => true, 'next_opening_at' => null]; // no schedule = always open (storefront rule)
        }
        $evaluator = new OpeningHoursEvaluator($s->hours, $s->timezone);
        $open = $evaluator->isOpenAt(now());

        return ['is_open' => $open, 'next_opening_at' => $open ? null : $evaluator->nextOpeningAfter(now())?->utc()->toIso8601String()];
    }

    /** @return array<string, mixed> a result card (one branch) */
    public static function card(MarketplaceStore $s, ?float $distanceKm = null): array
    {
        return [
            'store' => $s->store_slug,
            'branch' => $s->branch_slug,
            'name' => $s->name,
            'branch_name' => $s->branch_count > 1 ? $s->branch_name : null,
            'headline' => $s->headline,
            'city' => $s->city,
            'district' => $s->district,
            'categories' => MarketplaceCatalog::labelled($s->categories, MarketplaceCatalog::CATEGORIES),
            'amenities' => MarketplaceCatalog::labelled($s->amenities, MarketplaceCatalog::AMENITIES),
            'price_level' => $s->price_level,
            'offer' => $s->offers[0] ?? null,
            'free_delivery' => $s->free_delivery,
            'latitude' => $s->latitude === null ? null : (float) $s->latitude,
            'longitude' => $s->longitude === null ? null : (float) $s->longitude,
            'logo_url' => $s->logo_url,
            'cover_url' => $s->cover_url,
            'image_url' => $s->cover_url ?? ($s->highlights[0]['image_url'] ?? null),
            'primary_color' => $s->primary_color,
            'services' => $s->services,
            'is_featured' => $s->isFeatured(),
            'distance_km' => $distanceKm === null ? null : round($distanceKm, 1),
            ...self::openStatus($s),
        ];
    }

    /**
     * @param  Collection<int, MarketplaceStore>  $rows  every branch row of one café
     * @return array<string, mixed>
     */
    public static function profile(Collection $rows): array
    {
        /** @var MarketplaceStore $s */
        $s = $rows->first();

        return [
            'store' => $s->store_slug,
            'name' => $s->name,
            'headline' => $s->headline,
            'about' => $s->about,
            'categories' => MarketplaceCatalog::labelled($s->categories, MarketplaceCatalog::CATEGORIES),
            'amenities' => MarketplaceCatalog::labelled($s->amenities, MarketplaceCatalog::AMENITIES),
            'price_level' => $s->price_level,
            'logo_url' => $s->logo_url,
            'cover_url' => $s->cover_url,
            'primary_color' => $s->primary_color,
            'is_featured' => $s->isFeatured(),
            'services' => $s->services,
            'offers' => array_values(array_unique($rows->flatMap(fn (MarketplaceStore $b) => $b->offers ?? [])->all())),
            'dietary' => MarketplaceCatalog::labelled(array_values(array_filter(explode('|', $s->dietary_keys))), MarketplaceCatalog::DIETARY),
            'highlights' => array_map(fn (array $h) => ['name' => $h['name'], 'price_from' => $h['price_from'], 'image_url' => $h['image_url']], $s->highlights),
            'storefront_path' => '/s/'.$s->store_slug,
            'branches' => $rows->map(fn (MarketplaceStore $b) => [
                'slug' => $b->branch_slug,
                'name' => $b->branch_name,
                'city' => $b->city,
                'district' => $b->district,
                'province' => $b->province,
                'address' => $b->address,
                'phone' => $b->phone,
                'latitude' => $b->latitude === null ? null : (float) $b->latitude,
                'longitude' => $b->longitude === null ? null : (float) $b->longitude,
                'services' => $b->services,
                'hours' => array_map(fn (array $h) => ['weekday' => $h['weekday'], 'label' => Weekday::from($h['weekday'])->label(), 'opens_at' => $h['opens_at'], 'closes_at' => $h['closes_at']], $b->hours),
                ...self::openStatus($b),
            ])->values()->all(),
        ];
    }

    /** Great-circle distance in km. */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
