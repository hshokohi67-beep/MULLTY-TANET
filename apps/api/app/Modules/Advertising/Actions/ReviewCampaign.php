<?php

namespace App\Modules\Advertising\Actions;

use App\Modules\Advertising\Exceptions\AdException;
use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Support\AdPricing;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;

/**
 * Platform moderation. Call inside the campaign's tenant context. Approving a campaign that was
 * already paid (a payment that needed review) switches it straight on.
 */
final class ReviewCampaign
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(AdCampaign $campaign): AdCampaign
    {
        $this->expect($campaign, AdCampaign::PENDING);
        $placement = AdPlacement::query()->where('key', $campaign->placement)->first() ?? throw AdException::placementUnavailable();
        $taken = AdPricing::taken($campaign->placement, CarbonImmutable::instance($campaign->starts_at), CarbonImmutable::instance($campaign->ends_at), $campaign->id);
        if ($taken >= $placement->capacity) {
            throw AdException::full();
        }

        $campaign->update([
            'status' => $campaign->paid_at !== null ? AdCampaign::PAID : AdCampaign::APPROVED,
            'reviewed_at' => now(), 'review_note' => null, 'payment_issue' => null,
        ]);
        $this->audit->record('platform.ad_approved', $campaign, ['status' => $campaign->status]);

        return $campaign;
    }

    public function reject(AdCampaign $campaign, string $reason): AdCampaign
    {
        $this->expect($campaign, AdCampaign::PENDING);
        $campaign->update(['status' => AdCampaign::REJECTED, 'reviewed_at' => now(), 'review_note' => $reason]);
        $this->audit->record('platform.ad_rejected', $campaign, ['reason' => $reason, 'paid' => $campaign->paid_at !== null]);

        return $campaign;
    }

    public function suspend(AdCampaign $campaign, string $reason): AdCampaign
    {
        $this->expect($campaign, AdCampaign::PAID);
        $campaign->update(['status' => AdCampaign::SUSPENDED, 'suspended_at' => now(), 'review_note' => $reason]);
        $this->audit->record('platform.ad_suspended', $campaign, ['reason' => $reason]);

        return $campaign;
    }

    public function resume(AdCampaign $campaign): AdCampaign
    {
        $this->expect($campaign, AdCampaign::SUSPENDED);
        $campaign->update(['status' => AdCampaign::PAID, 'suspended_at' => null, 'review_note' => null]);
        $this->audit->record('platform.ad_resumed', $campaign, []);

        return $campaign;
    }

    private function expect(AdCampaign $campaign, string $status): void
    {
        if ($campaign->status !== $status) {
            throw AdException::wrongState();
        }
    }
}
