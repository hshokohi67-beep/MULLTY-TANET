<?php

namespace App\Modules\Commerce\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A table "visit": opened by the first QR scan, shared by everyone at the table,
 * closed by staff or after inactivity. Each device still has its own cart and order.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $table_id
 * @property string $status
 * @property Carbon $opened_at
 * @property Carbon $last_activity_at
 * @property ?Carbon $closed_at
 * @property RestaurantTable $table
 */
#[Fillable(['branch_id', 'table_id', 'status', 'opened_at', 'last_activity_at', 'closed_at'])]
class OrderSession extends Model
{
    use BelongsToTenant, HasUlids;

    public const IDLE_MINUTES = 180;

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'last_activity_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /**
     * Everyone at the table gets the same token (it is derived, not stored):
     * HMAC(app key, session id). It stops working once the session is closed.
     */
    public function accessToken(): string
    {
        return $this->id.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', 'order-session|'.$this->id, (string) config('app.key'), true)), '+/', '-_'), '=');
    }

    public function isUsable(): bool
    {
        return $this->status === 'open' && $this->last_activity_at->greaterThan(now()->subMinutes(self::IDLE_MINUTES));
    }
}
