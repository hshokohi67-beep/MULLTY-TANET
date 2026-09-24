<?php

namespace App\Modules\Kitchen\Listeners;

use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Kitchen\Actions\ReleasePreorders;
use App\Modules\Kitchen\Actions\RouteOrderToKitchen;
use App\Modules\Kitchen\Actions\UpdateKitchenItems;
use Throwable;

/**
 * Orders reach the kitchen when they are placed (online orders only after payment), and the
 * kitchen follows when the front of house cancels or finishes an order by hand.
 * A kitchen failure must never break ordering, so errors are reported, not thrown.
 */
final class KitchenOrderListener
{
    public function placed(OrderPlaced $event): void
    {
        // A pre-order waits: `kitchen:release-preorders` routes it shortly before its slot.
        if (ReleasePreorders::isWaiting($event->order)) {
            return;
        }

        try {
            app(RouteOrderToKitchen::class)->handle($event->order);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        try {
            app(UpdateKitchenItems::class)->followOrder($event->order, $event->to);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
