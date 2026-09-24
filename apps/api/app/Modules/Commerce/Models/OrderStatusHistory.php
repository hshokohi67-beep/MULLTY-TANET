<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only.
 *
 * @property ?OrderStatus $from_status
 * @property OrderStatus $to_status
 * @property ?string $actor_type
 * @property ?string $actor_id
 * @property ?string $note
 * @property Carbon $created_at
 */
#[Table('order_status_history')]
#[Fillable(['order_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'note'])]
class OrderStatusHistory extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class, 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Order history is append-only.'));
    }
}
