<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\TableRequestType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_session_id
 * @property string $table_id
 * @property TableRequestType $type
 * @property string $status
 * @property ?string $acknowledged_by
 * @property ?Carbon $acknowledged_at
 * @property Carbon $created_at
 * @property RestaurantTable $table
 */
#[Fillable(['order_session_id', 'table_id', 'type', 'status', 'acknowledged_by', 'acknowledged_at'])]
class TableSessionRequest extends Model
{
    use BelongsToTenant, HasUlids;

    public const COOLDOWN_SECONDS = 60;

    protected function casts(): array
    {
        return ['type' => TableRequestType::class, 'acknowledged_at' => 'datetime'];
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }
}
