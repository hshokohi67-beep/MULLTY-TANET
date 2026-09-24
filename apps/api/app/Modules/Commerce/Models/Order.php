<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\OrderSource;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Customers\Models\Customer;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An order is a frozen snapshot: names, prices, modifiers, discount and address are copied in,
 * so later menu or address changes never alter it. Amounts are integer rial.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property Carbon $business_date
 * @property int $daily_number
 * @property ?string $customer_id
 * @property ?string $table_id
 * @property ?string $order_session_id
 * @property OrderType $type
 * @property OrderSource $source
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property ?string $payment_method_intent
 * @property ?Carbon $scheduled_for
 * @property ?string $customer_note
 * @property ?string $contact_name
 * @property ?string $contact_phone_e164
 * @property ?array<string, mixed> $address_snapshot
 * @property ?string $delivery_zone_id
 * @property int $subtotal
 * @property int $discount_total
 * @property int $delivery_fee
 * @property int $total
 * @property int $paid_total maintained by the Payments module
 * @property int $refunded_total
 * @property ?array<string, mixed> $discount_snapshot
 * @property ?string $idempotency_key
 * @property Carbon $placed_at
 * @property ?Carbon $accepted_at
 * @property ?Carbon $completed_at
 * @property ?Carbon $cancelled_at
 * @property ?string $cancel_reason
 * @property Branch $branch
 * @property ?RestaurantTable $table
 * @property ?Customer $customer
 */
#[Fillable([
    'branch_id', 'business_date', 'daily_number', 'customer_id', 'table_id', 'order_session_id',
    'type', 'source', 'status', 'payment_status', 'payment_method_intent', 'scheduled_for',
    'customer_note', 'contact_name', 'contact_phone_e164', 'address_snapshot', 'delivery_zone_id',
    'subtotal', 'discount_total', 'delivery_fee', 'total', 'discount_snapshot', 'idempotency_key',
    'placed_at', 'accepted_at', 'completed_at', 'cancelled_at', 'cancel_reason',
])]
#[Hidden(['idempotency_key'])]
class Order extends Model
{
    use BelongsToTenant, HasUlids;

    /** @var array<string, mixed> */
    protected $attributes = ['paid_total' => 0, 'refunded_total' => 0];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'business_date' => 'date:Y-m-d',
            'daily_number' => 'integer',
            'scheduled_for' => 'datetime',
            'address_snapshot' => 'array',
            'discount_snapshot' => 'array',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'paid_total' => 'integer',
            'refunded_total' => 'integer',
            'placed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Customer-facing tracking token, derived (never stored): HMAC(app key, order id).
     * Lets an idempotent checkout replay return the same link again.
     */
    public function trackingToken(): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'order-tracking|'.$this->id, (string) config('app.key'), true)), '+/', '-_'), '=');
    }

    /** What is still owed: total minus what was paid and not refunded. Never negative. */
    public function remainingDue(): int
    {
        return max(0, $this->total - ($this->paid_total - $this->refunded_total));
    }

    /** Money is held for an order that won't be fulfilled, or more than the total was paid. */
    public function needsRefund(): bool
    {
        $net = $this->paid_total - $this->refunded_total;

        return $net > $this->total || ($net > 0 && in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true));
    }

    public function verifyTrackingToken(?string $token): bool
    {
        return is_string($token) && hash_equals($this->trackingToken(), $token);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
