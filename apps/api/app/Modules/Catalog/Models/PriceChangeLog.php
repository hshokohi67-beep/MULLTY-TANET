<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\PriceChangeReason;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only price history.
 *
 * @property string $variant_id
 * @property ?string $branch_id
 * @property ?int $old_amount
 * @property ?int $new_amount
 * @property PriceChangeReason $reason
 * @property ?string $batch_id
 * @property ?string $actor_id
 * @property Carbon $created_at
 */
#[Fillable(['variant_id', 'branch_id', 'old_amount', 'new_amount', 'reason', 'batch_id', 'actor_id'])]
class PriceChangeLog extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_amount' => 'integer',
            'new_amount' => 'integer',
            'reason' => PriceChangeReason::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Price history is append-only.'));
    }
}
