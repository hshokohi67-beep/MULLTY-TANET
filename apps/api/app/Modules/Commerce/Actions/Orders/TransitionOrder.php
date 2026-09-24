<?php

namespace App\Modules\Commerce\Actions\Orders;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderCompleted;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountUsage;
use Illuminate\Support\Facades\DB;

/**
 * The only way an order's status changes. Validates against the state machine, stamps
 * timestamps, writes history, and fires events after commit. A cancelled/rejected order gives
 * its discount usage back, so an abandoned online payment doesn't burn a customer's coupon.
 */
final class TransitionOrder
{
    public function handle(Order $order, OrderStatus $to, ?string $actorType = null, ?string $actorId = null, ?string $note = null): Order
    {
        $from = DB::transaction(function () use ($order, $to, $actorType, $actorId, $note): OrderStatus {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to, $locked->type)) {
                throw CommerceException::invalidTransition($from->label(), $to->label());
            }

            $locked->status = $to;

            match ($to) {
                OrderStatus::Placed => $locked->placed_at = now(), // released after online payment
                OrderStatus::Accepted => $locked->accepted_at = now(),
                OrderStatus::Completed => $locked->completed_at = now(),
                OrderStatus::Cancelled, OrderStatus::Rejected => [$locked->cancelled_at = now(), $locked->cancel_reason = $note],
                default => null,
            };

            $locked->save();

            if (in_array($to, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                $this->releaseDiscount($locked);
            }

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'note' => $note,
            ]);

            return $from;
        });

        $order->refresh();

        DB::afterCommit(function () use ($order, $from, $to): void {
            OrderStatusChanged::dispatch($order, $from, $to);

            if ($to === OrderStatus::Completed) {
                OrderCompleted::dispatch($order);
            }
        });

        return $order;
    }

    private function releaseDiscount(Order $order): void
    {
        foreach (DiscountUsage::query()->where('order_id', $order->id)->get() as $usage) {
            Discount::query()->whereKey($usage->discount_id)->where('used_count', '>', 0)->decrement('used_count');
            $usage->delete();
        }
    }
}
