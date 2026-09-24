<?php

namespace App\Modules\Payments\Models;

use App\Modules\Commerce\Models\Order;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One payment (or online attempt) towards an order. Amounts are integer rial.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property PaymentMethod $method
 * @property ?string $gateway
 * @property PaymentAttemptStatus $status
 * @property int $amount
 * @property int $refunded_amount
 * @property ?string $authority
 * @property ?string $ref_id
 * @property ?string $card_pan
 * @property ?int $fee
 * @property ?string $idempotency_key
 * @property ?string $reference
 * @property ?string $note
 * @property ?string $recorded_by
 * @property ?string $failure_code
 * @property ?Carbon $expires_at
 * @property ?Carbon $paid_at
 * @property ?Carbon $failed_at
 * @property Carbon $created_at
 * @property Order $order
 */
#[Fillable([
    'order_id', 'method', 'gateway', 'status', 'amount', 'refunded_amount', 'authority', 'ref_id', 'card_pan', 'fee',
    'idempotency_key', 'reference', 'note', 'recorded_by', 'failure_code', 'expires_at', 'paid_at', 'failed_at',
])]
#[Hidden(['idempotency_key', 'authority'])]
class Payment extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentAttemptStatus::class,
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'fee' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function refundable(): int
    {
        return $this->status === PaymentAttemptStatus::Paid ? max(0, $this->amount - $this->refunded_amount) : 0;
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<PaymentRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->orderBy('created_at')->orderBy('id');
    }
}
