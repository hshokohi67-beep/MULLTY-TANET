<?php

namespace App\Modules\Analytics\Support;

use App\Modules\Commerce\Enums\OrderStatus;

/**
 * What counts as a sale, shared by the rollup and the live dashboard queries: every order except
 * cancelled, rejected and online orders still waiting for payment.
 */
final class SalesRules
{
    public const EXCLUDED = [OrderStatus::Cancelled, OrderStatus::Rejected, OrderStatus::PendingPayment];

    public static function counts(OrderStatus $status): bool
    {
        return ! in_array($status, self::EXCLUDED, true);
    }

    public static function isCancelled(OrderStatus $status): bool
    {
        return $status === OrderStatus::Cancelled || $status === OrderStatus::Rejected;
    }
}
