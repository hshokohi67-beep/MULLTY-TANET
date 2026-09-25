<?php

namespace App\Modules\Advertising\Actions;

use App\Modules\Advertising\Exceptions\AdException;
use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Advertising\Support\AdPricing;
use App\Modules\Advertising\Support\CampaignInvoices;
use App\Modules\Billing\Actions\ManageBilling;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pays an approved campaign through the platform billing flow: one open `ad` invoice per campaign
 * (reused while its amount still matches), then the gateway, called outside any transaction.
 * The amount always comes from the stored quote, never from the request.
 */
final class PayCampaign
{
    public function __construct(
        private readonly ManageBilling $billing,
        private readonly TenantContext $context,
    ) {}

    public function handle(AdCampaign $campaign, ?string $userId): string
    {
        $this->assertPayable($campaign);

        $invoice = DB::transaction(function () use ($campaign, $userId): BillingInvoice {
            $open = CampaignInvoices::open($campaign);
            if ($open !== null && $open->subtotal === $campaign->amount) {
                return $open;
            }
            CampaignInvoices::voidOpen($campaign);
            $placement = AdPlacement::query()->where('key', $campaign->placement)->firstOrFail();
            $label = sprintf('تبلیغ «%s» • %s • %s روز', $campaign->name, $placement->name, $campaign->days);
            $invoice = $this->billing->createPurchase(CampaignInvoices::KIND, $campaign->id, [['label' => $label, 'amount' => $campaign->amount]], $userId);
            $campaign->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });

        return $this->billing->startPayment($invoice, '/ads/return');
    }

    private function assertPayable(AdCampaign $campaign): void
    {
        if ($campaign->status !== AdCampaign::APPROVED) {
            throw AdException::wrongState();
        }
        $timezone = $this->context->require()->timezone;
        if (CarbonImmutable::parse($campaign->start_date->toDateString(), $timezone)->lt(CarbonImmutable::now($timezone)->startOfDay())) {
            throw AdException::startPassed();
        }
        if (! MarketplaceStore::query()->where('tenant_id', $campaign->tenant_id)->exists()) {
            throw AdException::notListed();
        }
        $placement = AdPlacement::query()->where('key', $campaign->placement)->first() ?? throw AdException::placementUnavailable();
        if (! AdPricing::quote($placement, $campaign->start_date->toDateString(), $campaign->days, $timezone, $campaign->id)['available']) {
            throw AdException::full();
        }
    }
}
