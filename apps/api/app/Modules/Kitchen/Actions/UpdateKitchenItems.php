<?php

namespace App\Modules\Kitchen\Actions;

use App\Modules\Commerce\Actions\Orders\TransitionOrder;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Kitchen\Enums\KitchenItemStatus;
use App\Modules\Kitchen\Events\KitchenBoardChanged;
use App\Modules\Kitchen\Exceptions\KitchenException;
use App\Modules\Kitchen\Models\KitchenEvent;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Support\KitchenActor;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Every kitchen state change, and the order status that follows from it:
 *  - the first item started → the order is preparing (placed → accepted → preparing);
 *  - every non-cancelled item ready → the order is ready, then (D9) completed for the order types
 *    the tenant auto-completes. Delivery is never auto-completed.
 * Order row first, then items: the same lock order as every other order write.
 */
final class UpdateKitchenItems
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /** start / ready / recall one item. */
    public function item(KitchenItem $item, KitchenItemStatus $to, KitchenActor $actor): KitchenItem
    {
        $order = $this->change($item->order_id, $actor, function (Order $order) use ($item, $to, $actor): void {
            /** @var KitchenItem $locked */
            $locked = KitchenItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if (! $actor->canReach($order->branch_id, $locked->station_id)) {
                throw new NotFoundHttpException;
            }

            if ($to === KitchenItemStatus::Preparing && $locked->status === KitchenItemStatus::Ready && in_array($order->status, [OrderStatus::Ready, OrderStatus::OutForDelivery], true)) {
                throw KitchenException::orderClosed(); // too late to recall: the order was handed over
            }

            $this->move($locked, $to, $actor);
        });

        return $item->refresh()->setRelation('order', $order);
    }

    /** Marks every open item of an order at one station ready. */
    public function bump(Order $order, string $stationId, KitchenActor $actor): Order
    {
        return $this->change($order->id, $actor, function (Order $locked) use ($stationId, $actor): void {
            if (! $actor->canReach($locked->branch_id, $stationId)) {
                throw new NotFoundHttpException;
            }

            $items = KitchenItem::query()->where('order_id', $locked->id)->where('station_id', $stationId)
                ->whereIn('status', [KitchenItemStatus::Queued, KitchenItemStatus::Preparing])->lockForUpdate()->get();

            foreach ($items as $item) {
                $this->move($item, KitchenItemStatus::Ready, $actor, 'bumped');
            }
        });
    }

    /**
     * The front of house moved the order on its own (cancelled, or ready/completed by hand):
     * bring the kitchen items in line so the board clears. Never touches order status.
     */
    public function followOrder(Order $order, OrderStatus $to): void
    {
        $target = match ($to) {
            OrderStatus::Cancelled, OrderStatus::Rejected => KitchenItemStatus::Cancelled,
            OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Completed => KitchenItemStatus::Ready,
            default => null,
        };

        if ($target === null) {
            return;
        }

        $changed = DB::transaction(function () use ($order, $target): int {
            $items = KitchenItem::query()->where('order_id', $order->id)
                ->whereNotIn('status', $target === KitchenItemStatus::Cancelled ? [KitchenItemStatus::Cancelled] : [KitchenItemStatus::Ready, KitchenItemStatus::Cancelled])
                ->lockForUpdate()->get();

            foreach ($items as $item) {
                $this->move($item, $target, new KitchenActor('system', 'system'), $target === KitchenItemStatus::Cancelled ? 'cancelled' : 'closed_by_front');
            }

            return $items->count();
        });

        if ($changed > 0) {
            DB::afterCommit(fn () => KitchenBoardChanged::dispatch($order->tenant_id, $order->branch_id));
        }
    }

    /**
     * @param  callable(Order): void  $change
     */
    private function change(string $orderId, KitchenActor $actor, callable $change): Order
    {
        $order = DB::transaction(function () use ($orderId, $actor, $change): Order {
            /** @var Order $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            if (! $actor->canReach($order->branch_id)) {
                throw new NotFoundHttpException;
            }

            if ($order->status->isFinal() || $order->status === OrderStatus::PendingPayment) {
                throw KitchenException::orderClosed();
            }

            $change($order);
            $this->syncOrder($order, $actor);

            return $order;
        });

        DB::afterCommit(fn () => KitchenBoardChanged::dispatch($order->tenant_id, $order->branch_id));

        return $order->refresh();
    }

    private function move(KitchenItem $item, KitchenItemStatus $to, KitchenActor $actor, ?string $eventType = null): void
    {
        if ($item->status === $to) {
            return;
        }

        if (! $item->status->canBecome($to)) {
            throw KitchenException::invalidStep($item->status->label(), $to->label());
        }

        $item->forceFill([
            'status' => $to,
            'started_at' => $to === KitchenItemStatus::Preparing ? ($item->started_at ?? now()) : $item->started_at,
            'ready_at' => $to === KitchenItemStatus::Ready ? now() : ($to === KitchenItemStatus::Preparing ? null : $item->ready_at),
        ])->save();

        KitchenEvent::query()->create([
            'order_id' => $item->order_id,
            'kitchen_item_id' => $item->id,
            'station_id' => $item->station_id,
            'type' => $eventType ?? match ($to) {
                KitchenItemStatus::Preparing => $item->getOriginal('status') === KitchenItemStatus::Ready ? 'recalled' : 'started',
                KitchenItemStatus::Ready => 'ready',
                default => $to->value,
            },
            'actor_type' => $actor->type,
            'actor_id' => $actor->type === 'system' ? null : $actor->id,
        ]);
    }

    private function syncOrder(Order $order, KitchenActor $actor): void
    {
        $statuses = KitchenItem::query()->where('order_id', $order->id)->where('status', '!=', KitchenItemStatus::Cancelled)->pluck('status');
        $allReady = $statuses->isNotEmpty() && $statuses->every(fn (KitchenItemStatus $s) => $s === KitchenItemStatus::Ready);
        $anyStarted = $statuses->contains(fn (KitchenItemStatus $s) => $s !== KitchenItemStatus::Queued);
        $by = [$actor->type, $actor->type === 'system' ? null : $actor->id];

        // Only ever forward, through the order state machine.
        $path = match (true) {
            $allReady => [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready],
            $anyStarted => [OrderStatus::Accepted, OrderStatus::Preparing],
            default => [],
        };

        foreach ($path as $step) {
            $current = $order->refresh()->status;
            if ($current->canTransitionTo($step, $order->type)) {
                $this->transition->handle($order, $step, $by[0], $by[1], 'آشپزخانه');
            }
        }

        if ($allReady && $order->refresh()->status === OrderStatus::Ready && $this->autoCompletes($order->type)) {
            $this->transition->handle($order, OrderStatus::Completed, $by[0], $by[1], 'تکمیل خودکار پس از آماده شدن');
        }
    }

    private function autoCompletes(OrderType $type): bool
    {
        return match ($type) {
            OrderType::QrTable, OrderType::DineIn, OrderType::Counter => (bool) TenantSettings::get('kds.auto_complete_dine_in'),
            OrderType::Takeaway, OrderType::Phone, OrderType::Online => (bool) TenantSettings::get('kds.auto_complete_takeaway'),
            OrderType::Delivery => false,
        };
    }
}
