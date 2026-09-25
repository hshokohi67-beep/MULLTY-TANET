<?php

namespace App\Modules\Advertising\Support;

use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Models\AdSlot;
use App\Modules\Marketplace\Models\MarketplaceStore;
use Illuminate\Support\Facades\Storage;

/**
 * The only way ad data leaves the API: explicit key lists. Public shapes carry a signed event
 * token and a link the server built (always the café's own page), never ids.
 */
final class AdPresenter
{
    /**
     * @param  array{impressions: int, clicks: int}  $totals
     * @return array<string, mixed> the café's (and the platform's) view of a campaign
     */
    public static function campaign(AdCampaign $c, array $totals = ['impressions' => 0, 'clicks' => 0]): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'placement' => $c->placement,
            'status' => $c->status,
            'phase' => $c->phase(),
            'start_date' => $c->start_date->toDateString(),
            'days' => $c->days,
            'starts_at' => $c->starts_at->toIso8601String(),
            'ends_at' => $c->ends_at->toIso8601String(),
            'cities' => $c->cities,
            'headline' => $c->headline,
            'body' => $c->body,
            'cta' => $c->cta,
            'cta_label' => AdCampaign::CTAS[$c->cta] ?? AdCampaign::CTAS['visit'],
            'image_url' => self::url($c->image_path),
            'image_small_url' => self::url($c->image_small_path),
            'daily_price' => $c->daily_price,
            'amount' => $c->amount,
            'review_note' => $c->review_note,
            'payment_issue' => $c->payment_issue,
            'submitted_at' => $c->submitted_at?->toIso8601String(),
            'reviewed_at' => $c->reviewed_at?->toIso8601String(),
            'paid_at' => $c->paid_at?->toIso8601String(),
            'suspended_at' => $c->suspended_at?->toIso8601String(),
            'editable' => in_array($c->status, AdCampaign::EDITABLE, true),
            'impressions' => $totals['impressions'],
            'clicks' => $totals['clicks'],
            'ctr' => $totals['impressions'] > 0 ? round($totals['clicks'] / $totals['impressions'] * 100, 1) : null,
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function placement(AdPlacement $p): array
    {
        return [
            'key' => $p->key, 'name' => $p->name, 'description' => $p->description, 'daily_price' => $p->daily_price,
            'capacity' => $p->capacity, 'requires_image' => $p->requires_image, 'is_active' => $p->is_active,
        ];
    }

    /** @return array<string, mixed> a public home banner */
    public static function banner(AdSlot $s, MarketplaceStore $store): array
    {
        return [
            'token' => AdTokens::make($s->ref, $s->placement),
            'store' => $s->store_slug,
            'name' => $store->name,
            'city' => $store->city,
            'headline' => $s->headline,
            'body' => $s->body,
            'cta_label' => AdCampaign::CTAS[$s->cta] ?? AdCampaign::CTAS['visit'],
            'href' => self::href($s),
            'image_url' => $s->image_url,
            'image_small_url' => $s->image_small_url,
            'logo_url' => $store->logo_url,
            'primary_color' => $store->primary_color,
        ];
    }

    /** @return array<string, mixed> the «تبلیغ» extra on a sponsored result card */
    public static function sponsored(AdSlot $s): array
    {
        return [
            'token' => AdTokens::make($s->ref, $s->placement),
            'headline' => $s->headline,
            'body' => $s->body,
            'cta_label' => AdCampaign::CTAS[$s->cta] ?? AdCampaign::CTAS['visit'],
            'href' => self::href($s),
        ];
    }

    public static function url(?string $path): ?string
    {
        return $path === null ? null : Storage::disk(config('filesystems.media_disk'))->url($path);
    }

    private static function href(AdSlot $s): string
    {
        return in_array($s->cta, ['menu', 'order'], true) ? '/s/'.$s->store_slug : '/explore/'.$s->store_slug;
    }
}
