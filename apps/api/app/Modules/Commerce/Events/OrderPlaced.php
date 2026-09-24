<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after the checkout transaction commits. KDS (Phase 6) and notifications subscribe. */
final class OrderPlaced
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
