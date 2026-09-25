<?php

namespace App\Modules\Marketplace\Models;

use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Public read model (platform-level, deliberately NOT tenant-scoped): one row per branch of an
 * eligible listed café. Written only by `ProjectStore`; serialised only by `StorePresenter`.
 *
 * @property string $id
 * @property string $tenant_id internal, never serialised
 * @property string $store_slug
 * @property string $branch_slug
 * @property string $name
 * @property string $branch_name
 * @property int $branch_count
 * @property ?string $headline
 * @property ?string $about
 * @property string $city
 * @property ?string $district
 * @property ?list<string> $offers
 * @property bool $has_offer
 * @property string $dietary_keys
 * @property bool $closes_late
 * @property bool $free_delivery
 * @property ?string $province
 * @property ?string $address
 * @property ?string $phone
 * @property ?string $latitude
 * @property ?string $longitude
 * @property list<string> $categories
 * @property list<string> $amenities
 * @property ?int $price_level
 * @property ?string $logo_url
 * @property ?string $cover_url
 * @property ?string $primary_color
 * @property list<array{weekday: int, opens_at: string, closes_at: string}> $hours
 * @property string $timezone
 * @property array<string, bool> $services
 * @property list<array{name: string, price_from: ?int, image_url: ?string}> $highlights
 * @property string $search_text
 * @property int $popularity internal, never serialised
 * @property ?Carbon $featured_until
 * @property ?Carbon $listed_at
 */
class MarketplaceStore extends Model
{
    use HasUlids, StoresDatesInUtc;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'categories' => 'array', 'amenities' => 'array', 'offers' => 'array', 'has_offer' => 'boolean', 'closes_late' => 'boolean', 'free_delivery' => 'boolean', 'hours' => 'array', 'services' => 'array', 'highlights' => 'array',
            'branch_count' => 'integer', 'price_level' => 'integer', 'popularity' => 'integer',
            'featured_until' => 'datetime', 'listed_at' => 'datetime',
        ];
    }

    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }
}
