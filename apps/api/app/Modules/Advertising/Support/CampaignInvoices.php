<?php

namespace App\Modules\Advertising\Support;

use App\Modules\Advertising\Exceptions\AdException;
use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\BillingPayment;

/** The billing invoices of a campaign (kind `ad`, subject = the campaign). */
final class CampaignInvoices
{
    public const KIND = 'ad';

    public static function open(AdCampaign $campaign): ?BillingInvoice
    {
        return BillingInvoice::query()->where('kind', self::KIND)->where('subject_id', $campaign->id)->where('status', 'open')->latest()->first();
    }

    /** Someone is at the gateway right now: changing the price under them would lose the payment. */
    public static function assertNoPaymentInProgress(AdCampaign $campaign): void
    {
        $open = BillingInvoice::query()->where('kind', self::KIND)->where('subject_id', $campaign->id)->where('status', 'open')->pluck('id');
        if ($open->isNotEmpty() && BillingPayment::query()->whereIn('invoice_id', $open)->where('status', 'pending')->where('created_at', '>', now()->subMinutes(20))->exists()) {
            throw AdException::paymentInProgress();
        }
    }

    public static function voidOpen(AdCampaign $campaign): void
    {
        BillingInvoice::query()->where('kind', self::KIND)->where('subject_id', $campaign->id)->where('status', 'open')->update(['status' => 'void']);
    }
}
