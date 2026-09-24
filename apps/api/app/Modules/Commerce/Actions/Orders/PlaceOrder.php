<?php

namespace App\Modules\Commerce\Actions\Orders;

use App\Modules\Commerce\Data\CheckoutData;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderCounter;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderItemModifier;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Commerce\Support\Pricing\OrderPricer;
use App\Modules\Commerce\Support\Pricing\PricingRequest;
use App\Modules\Discounts\Exceptions\CouponException;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountUsage;
use App\Modules\Discounts\Support\AppliedDiscount;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Checkout. Everything is recomputed server-side inside one transaction, then frozen:
 * item names/prices/modifiers, discount and address are copied into the order.
 * Idempotent: the same Idempotency-Key returns the original order instead of a duplicate.
 */
final class PlaceOrder
{
    public function __construct(
        private readonly OrderPricer $pricer,
        private readonly TenantContext $context,
    ) {}

    /** @return array{order: Order, replayed: bool} */
    public function handle(CheckoutData $data): array
    {
        $existing = Order::query()->where('idempotency_key', $data->idempotencyKey)->first();

        if ($existing !== null) {
            return ['order' => $this->replay($existing, $data), 'replayed' => true];
        }

        try {
            $order = $this->place($data);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race: return its order.
            $winner = Order::query()->where('idempotency_key', $data->idempotencyKey)->firstOrFail();

            return ['order' => $this->replay($winner, $data), 'replayed' => true];
        }

        // An order waiting for online payment reaches the kitchen only once the payment is verified.
        if ($order->status === OrderStatus::Placed) {
            DB::afterCommit(fn () => OrderPlaced::dispatch($order));
        }

        return ['order' => $order, 'replayed' => false];
    }

    /** A replayed key must belong to the same buyer; otherwise it would reveal someone else's order. */
    private function replay(Order $order, CheckoutData $data): Order
    {
        if ($order->customer_id !== $data->customer?->getKey() || $order->order_session_id !== $data->session?->getKey()) {
            throw CommerceException::idempotencyConflict();
        }

        return $order;
    }

    private function place(CheckoutData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $priced = $this->pricer->price(new PricingRequest(
                branch: $data->branch,
                orderType: $data->type,
                lines: $data->lines,
                customerId: $data->customer?->getKey(),
                couponCode: $data->couponCode,
                address: $data->address,
                scheduledFor: $data->scheduledFor,
            ), strict: true);

            if ($priced->validLines() === []) {
                throw CommerceException::cartEmpty();
            }

            if ($data->paymentMethodIntent === 'online' && $priced->total < (int) config('payments.min_online_amount')) {
                throw CommerceException::onlineBelowMinimum(MoneyFormatter::format(Money::rials((int) config('payments.min_online_amount'))));
            }

            $discount = $priced->discount ? $this->lockAndRecheck($priced->discount, $data) : null;
            $initial = $data->paymentMethodIntent === 'online' ? OrderStatus::PendingPayment : OrderStatus::Placed;
            $businessDate = CarbonImmutable::now($this->context->require()->timezone)->toDateString();

            $order = Order::query()->create([
                'branch_id' => $data->branch->getKey(),
                'business_date' => $businessDate,
                'daily_number' => $this->nextNumber($data->branch->getKey(), $businessDate),
                'customer_id' => $data->customer?->getKey(),
                'table_id' => $data->session?->table_id,
                'order_session_id' => $data->session?->getKey(),
                'type' => $data->type,
                'source' => $data->source,
                'status' => $initial,
                'payment_status' => PaymentStatus::Unpaid,
                'payment_method_intent' => $data->paymentMethodIntent,
                'scheduled_for' => $priced->scheduledFor,
                'customer_note' => $data->note,
                'contact_name' => $data->contactName ?? $data->customer?->name,
                'contact_phone_e164' => $data->contactPhoneE164 ?? $data->customer?->phone_e164,
                'address_snapshot' => $data->address?->snapshot(),
                'delivery_zone_id' => $priced->delivery?->zone->id,
                'subtotal' => $priced->subtotal,
                'discount_total' => $priced->discountTotal(),
                'delivery_fee' => $priced->deliveryFee(),
                'total' => $priced->total,
                'discount_snapshot' => $discount?->snapshot(),
                'idempotency_key' => $data->idempotencyKey,
                'placed_at' => now(),
            ]);

            foreach ($priced->validLines() as $line) {
                $item = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'product_name' => $line->productName,
                    'variant_name' => $line->variantName,
                    'unit_price' => $line->unitPrice,
                    'modifiers_total' => $line->modifiersTotal,
                    'quantity' => $line->quantity,
                    'line_total' => $line->lineTotal,
                    'note' => $line->note,
                ]);

                foreach ($line->modifiers as $modifier) {
                    OrderItemModifier::query()->create(['order_item_id' => $item->id, ...$modifier]);
                }
            }

            if ($discount !== null) {
                DiscountUsage::query()->create([
                    'discount_id' => $discount->discount->id,
                    'order_id' => $order->id,
                    'customer_id' => $data->customer?->getKey(),
                    'amount' => $discount->amount,
                ]);
                Discount::query()->whereKey($discount->discount->id)->increment('used_count');
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $initial,
                'actor_type' => $data->actorType ?? ($data->customer ? 'customer' : 'guest'),
                'actor_id' => $data->actorId ?? $data->customer?->getKey(),
            ]);

            if ($data->cartId !== null) {
                Cart::query()->whereKey($data->cartId)->update(['status' => 'converted', 'customer_id' => $data->customer?->getKey()]);
            }

            return $order;
        });
    }

    /**
     * Re-checks usage limits under a row lock so two simultaneous checkouts can't both take
     * the last use of a coupon.
     */
    private function lockAndRecheck(AppliedDiscount $applied, CheckoutData $data): AppliedDiscount
    {
        $locked = Discount::query()->whereKey($applied->discount->id)->lockForUpdate()->firstOrFail();

        if ($locked->usage_limit !== null && $locked->used_count >= $locked->usage_limit) {
            throw CouponException::exhausted();
        }

        if ($locked->per_customer_limit !== null && $data->customer !== null
            && DiscountUsage::query()->where('discount_id', $locked->id)->where('customer_id', $data->customer->getKey())->count() >= $locked->per_customer_limit) {
            throw CouponException::alreadyUsed();
        }

        return $applied;
    }

    /** Daily per-branch order number ("order #23 today"), taken under a row lock. */
    private function nextNumber(string $branchId, string $date): int
    {
        $query = fn () => OrderCounter::query()->where('branch_id', $branchId)->where('date', $date)->lockForUpdate()->first();
        $counter = $query();

        if ($counter === null) {
            OrderCounter::query()->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'tenant_id' => $this->context->require()->getKey(),
                'branch_id' => $branchId,
                'date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $counter = $query() ?? throw new \RuntimeException('Order counter could not be created.');
        }

        $counter->increment('last_number');

        return $counter->last_number;
    }
}
