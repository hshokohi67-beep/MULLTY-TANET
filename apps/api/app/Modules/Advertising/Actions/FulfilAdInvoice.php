<?php

namespace App\Modules\Advertising\Actions;

use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Support\AdPricing;
use App\Modules\Billing\Contracts\InvoiceFulfiller;
use App\Modules\Billing\Models\BillingInvoice;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * A paid `ad` invoice turns its campaign on. Runs inside the billing transaction with the invoice
 * locked (exactly once). Money is never lost silently: if the campaign changed state meanwhile,
 * the amount doesn't match or the placement filled up, the campaign keeps the payment and goes
 * back to the platform's review queue with `payment_issue` set.
 */
final class FulfilAdInvoice implements InvoiceFulfiller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function fulfil(BillingInvoice $invoice): void
    {
        $campaign = AdCampaign::query()->lockForUpdate()->find($invoice->subject_id);
        if ($campaign === null) {
            Log::warning('ads.paid_invoice_without_campaign', ['invoice' => $invoice->id]);

            return;
        }

        $issue = match (true) {
            $campaign->status !== AdCampaign::APPROVED => 'state',
            $invoice->subtotal < $campaign->amount => 'amount',
            ! $this->hasRoom($campaign) => 'capacity',
            default => null,
        };

        $campaign->update([
            'invoice_id' => $invoice->id,
            'paid_at' => now(),
            'status' => $issue === null ? AdCampaign::PAID : AdCampaign::PENDING,
            'payment_issue' => $issue,
        ]);
        $this->audit->record('ads.campaign_paid', $campaign, ['invoice' => $invoice->number, 'issue' => $issue]);
    }

    private function hasRoom(AdCampaign $campaign): bool
    {
        $placement = AdPlacement::query()->where('key', $campaign->placement)->first();

        return $placement !== null && AdPricing::taken($campaign->placement, CarbonImmutable::instance($campaign->starts_at), CarbonImmutable::instance($campaign->ends_at), $campaign->id) < $placement->capacity;
    }
}
