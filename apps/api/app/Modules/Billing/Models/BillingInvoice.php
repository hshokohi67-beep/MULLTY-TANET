<?php

namespace App\Modules\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A platform invoice to a tenant (amounts in rial). Paying it applies the plan/cycle/add-ons it
 * describes to the subscription (`ApplyPaidInvoice`).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $number
 * @property string $kind checkout | renewal (subscription) or a registered kind such as `ad`
 * @property ?string $subject_id what a non-subscription invoice pays for
 * @property string $status
 * @property ?string $plan_id
 * @property ?string $cycle
 * @property list<array{addon_id: string, quantity: int}> $addons
 * @property ?string $mode
 * @property list<array{label: string, amount: int}> $lines
 * @property int $subtotal
 * @property int $credit
 * @property int $vat_rate
 * @property int $vat
 * @property int $total
 * @property ?Carbon $period_start
 * @property ?Carbon $period_end
 * @property ?Carbon $due_at
 * @property ?Carbon $paid_at
 * @property ?string $paid_via
 * @property ?string $reference
 * @property ?string $created_by
 * @property Carbon $created_at
 * @property ?Plan $plan
 */
#[Fillable(['number', 'kind', 'subject_id', 'status', 'plan_id', 'cycle', 'addons', 'mode', 'lines', 'subtotal', 'credit', 'vat_rate', 'vat', 'total', 'period_start', 'period_end', 'due_at', 'paid_at', 'paid_via', 'reference', 'created_by'])]
class BillingInvoice extends Model
{
    use BelongsToTenant, HasUlids;

    /** Kinds that change the subscription (applied by ApplyPaidInvoice). */
    public const SUBSCRIPTION_KINDS = ['checkout', 'renewal'];

    public function isSubscription(): bool
    {
        return in_array($this->kind, self::SUBSCRIPTION_KINDS, true);
    }

    protected function casts(): array
    {
        return [
            'addons' => 'array', 'lines' => 'array', 'subtotal' => 'integer', 'credit' => 'integer', 'vat_rate' => 'integer', 'vat' => 'integer', 'total' => 'integer',
            'period_start' => 'datetime', 'period_end' => 'datetime', 'due_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<BillingPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'invoice_id');
    }
}
