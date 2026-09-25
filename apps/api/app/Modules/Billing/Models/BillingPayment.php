<?php

namespace App\Modules\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One gateway attempt for an invoice. `log` keeps each request/response (without secrets).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property string $gateway
 * @property string $status
 * @property int $amount
 * @property ?string $authority
 * @property ?string $ref_id
 * @property ?string $card_pan
 * @property ?string $failure_code
 * @property ?list<array<string, mixed>> $log
 * @property ?Carbon $paid_at
 * @property BillingInvoice $invoice
 */
#[Fillable(['invoice_id', 'gateway', 'status', 'amount', 'authority', 'ref_id', 'card_pan', 'failure_code', 'log', 'paid_at'])]
class BillingPayment extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'log' => 'array', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<BillingInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }
}
