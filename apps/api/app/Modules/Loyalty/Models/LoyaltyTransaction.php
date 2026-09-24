<?php

namespace App\Modules\Loyalty\Models;

use App\Modules\Loyalty\Enums\PointsTransactionType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only points ledger row.
 *
 * @property string $id
 * @property string $account_id
 * @property PointsTransactionType $type
 * @property int $points
 * @property int $balance_after
 * @property ?string $order_id
 * @property ?string $description
 * @property ?string $actor_type
 * @property ?string $actor_id
 * @property ?string $idempotency_key
 * @property Carbon $created_at
 */
#[Fillable(['account_id', 'type', 'points', 'balance_after', 'order_id', 'description', 'actor_type', 'actor_id', 'idempotency_key'])]
#[Hidden(['idempotency_key'])]
class LoyaltyTransaction extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => PointsTransactionType::class, 'points' => 'integer', 'balance_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Loyalty transactions are append-only.'));
        static::deleting(fn () => throw new LogicException('Loyalty transactions are append-only.'));
    }
}
