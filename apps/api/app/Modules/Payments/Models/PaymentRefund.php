<?php

namespace App\Modules\Payments\Models;

use App\Modules\Payments\Enums\RefundMethod;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Money returned to the customer. Append-only.
 *
 * @property string $id
 * @property string $payment_id
 * @property int $amount
 * @property RefundMethod $method
 * @property ?string $reference
 * @property string $reason
 * @property ?string $idempotency_key
 * @property ?string $actor_id
 * @property Carbon $created_at
 */
#[Fillable(['payment_id', 'amount', 'method', 'reference', 'reason', 'idempotency_key', 'actor_id'])]
#[Hidden(['idempotency_key'])]
class PaymentRefund extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'method' => RefundMethod::class];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Refunds are append-only.'));
    }
}
