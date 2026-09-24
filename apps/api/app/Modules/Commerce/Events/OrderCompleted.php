<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** The hook for loyalty/cashback (Phase 5): fired once, when an order reaches "completed". */
final class OrderCompleted
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
