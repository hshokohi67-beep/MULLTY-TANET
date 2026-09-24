<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Core\Models\Branch;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A cart is addressed by an unguessable token (guests included). It never stores prices:
 * the pricer recomputes them from the live menu on every read.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property ?string $customer_id
 * @property ?string $order_session_id
 * @property OrderType $order_type
 * @property string $status
 * @property Carbon $expires_at
 * @property Branch $branch
 * @property ?OrderSession $session
 */
#[Fillable(['branch_id', 'customer_id', 'order_session_id', 'cart_token_hash', 'order_type', 'status', 'expires_at'])]
#[Hidden(['cart_token_hash'])]
class Cart extends Model
{
    use BelongsToTenant, HasUlids;

    public const TTL_DAYS = 3;

    public const MAX_LINES = 50;

    protected function casts(): array
    {
        return ['order_type' => OrderType::class, 'expires_at' => 'datetime'];
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<OrderSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(OrderSession::class, 'order_session_id');
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }
}
