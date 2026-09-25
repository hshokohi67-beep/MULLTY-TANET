<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Models\BillingInvoice;

/**
 * Delivers what a non-subscription invoice paid for (e.g. an ad campaign). Registered per invoice
 * kind by the module that sells it (`InvoiceFulfillers::register`), so Billing never depends on it.
 */
interface InvoiceFulfiller
{
    /**
     * Runs exactly once, inside the transaction that marks the invoice paid (the invoice row is
     * locked). Must not call external services; throwing rolls the payment marking back.
     */
    public function fulfil(BillingInvoice $invoice): void;
}
