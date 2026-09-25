<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Inventory\Actions\ConsumeOrderStock;
use App\Support\Entitlements\EntitlementGate;
use Throwable;

/**
 * Stock follows orders: placed (online orders only after payment) takes the recipe out; cancelled
 * or rejected puts it back. Stock bookkeeping must never break ordering, so errors are reported.
 */
final class InventoryOrderListener
{
    public function placed(OrderPlaced $event): void
    {
        // Without the inventory feature nothing is booked (restores still undo earlier bookings).
        if (! app(EntitlementGate::class)->enabled('inventory')) {
            return;
        }

        try {
            app(ConsumeOrderStock::class)->consume($event->order);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        if (! in_array($event->to, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            return;
        }

        try {
            app(ConsumeOrderStock::class)->restore($event->order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
