<?php

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched INSIDE the refund transaction, so listeners that move money (wallet credit,
 * reward reversal) commit or roll back together with the refund. Listeners must be synchronous.
 */
final class PaymentRefunded
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentRefund $refund,
    ) {}
}
