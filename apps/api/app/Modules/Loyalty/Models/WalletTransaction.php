<?php

namespace App\Modules\Loyalty\Models;

use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only wallet ledger row. amount is signed rial; balance_after is the wallet balance after it.
 *
 * @property string $id
 * @property string $wallet_id
 * @property WalletTransactionType $type
 * @property int $amount
 * @property int $balance_after
 * @property ?string $order_id
 * @property ?string $payment_id
 * @property ?string $description
 * @property ?string $actor_type
 * @property ?string $actor_id
 * @property ?string $idempotency_key
 * @property Carbon $created_at
 */
#[Fillable(['wallet_id', 'type', 'amount', 'balance_after', 'order_id', 'payment_id', 'description', 'actor_type', 'actor_id', 'idempotency_key'])]
#[Hidden(['idempotency_key'])]
class WalletTransaction extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => WalletTransactionType::class, 'amount' => 'integer', 'balance_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Wallet transactions are append-only.'));
        static::deleting(fn () => throw new LogicException('Wallet transactions are append-only.'));
    }
}
