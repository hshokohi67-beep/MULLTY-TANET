<?php

namespace App\Modules\Advertising\Actions;

use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdSlot;
use App\Modules\Advertising\Support\AdPresenter;
use App\Modules\Core\Models\Tenant;

/**
 * Keeps the public `ad_slots` row of a campaign in step: present only while the campaign is paid
 * (and not suspended), with an explicit column list. Serving adds the time window and the check
 * that the café is still in the marketplace.
 */
final class ProjectCampaign
{
    public function handle(AdCampaign $campaign): void
    {
        $tenant = Tenant::query()->find($campaign->tenant_id);
        if ($campaign->status !== AdCampaign::PAID || $tenant === null) {
            AdSlot::query()->where('campaign_id', $campaign->id)->delete();

            return;
        }

        AdSlot::query()->updateOrCreate(['campaign_id' => $campaign->id], [
            'tenant_id' => $campaign->tenant_id,
            'ref' => $campaign->ref,
            'placement' => $campaign->placement,
            'store_slug' => $tenant->slug,
            'city_keys' => $campaign->cities === [] ? '' : '|'.implode('|', $campaign->cities).'|',
            'headline' => $campaign->headline,
            'body' => $campaign->body,
            'cta' => $campaign->cta,
            'image_url' => AdPresenter::url($campaign->image_path),
            'image_small_url' => AdPresenter::url($campaign->image_small_path),
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ]);
    }
}
