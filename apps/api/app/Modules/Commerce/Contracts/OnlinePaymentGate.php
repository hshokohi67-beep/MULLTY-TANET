<?php

namespace App\Modules\Commerce\Contracts;

/**
 * Lets checkout ask whether online payment is possible without depending on the Payments module
 * (which depends on Commerce). Bound by the Payments module.
 */
interface OnlinePaymentGate
{
    public function onlineAvailable(): bool;
}
