<?php

namespace App\Modules\Advertising\Models;

use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Public read model of a paid, unsuspended campaign (platform-level, deliberately NOT tenant-scoped).
 * Written only by ProjectCampaign; serialised only by AdPresenter.
 *
 * @property string $id
 * @property string $tenant_id internal, never serialised
 * @property string $campaign_id internal, never serialised
 * @property string $ref
 * @property string $placement
 * @property string $store_slug
 * @property string $city_keys
 * @property string $headline
 * @property ?string $body
 * @property string $cta
 * @property ?string $image_url
 * @property ?string $image_small_url
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
class AdSlot extends Model
{
    use HasUlids, StoresDatesInUtc;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLive(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /**
     * Slots aimed at this city or at every city. With no city known, only untargeted slots:
     * a café that paid for Shiraz must not be shown to someone who may be in Tabriz.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForCity(Builder $query, ?string $city): Builder
    {
        if ($city === null || $city === '') {
            return $query->where('city_keys', '');
        }

        return $query->where(fn (Builder $q) => $q->where('city_keys', '')->orWhere('city_keys', 'like', '%|'.addcslashes($city, '%_\\').'|%'));
    }
}
