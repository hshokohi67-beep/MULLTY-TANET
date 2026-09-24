<?php

namespace App\Modules\Kitchen\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only kitchen audit trail.
 *
 * @property string $id
 * @property string $order_id
 * @property ?string $kitchen_item_id
 * @property ?string $station_id
 * @property string $type routed | started | ready | recalled | cancelled | bumped
 * @property string $actor_type
 * @property ?string $actor_id
 * @property Carbon $created_at
 */
#[Fillable(['order_id', 'kitchen_item_id', 'station_id', 'type', 'actor_type', 'actor_id'])]
class KitchenEvent extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Kitchen events are append-only.'));
    }
}
