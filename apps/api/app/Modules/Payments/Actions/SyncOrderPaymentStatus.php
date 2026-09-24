<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Models\Payment;

/**
 * The only writer of an order's payment_status / paid_total / refunded_total.
 * Always derived from the payments themselves, never set by hand.
 */
final class SyncOrderPaymentStatus
{
    public function handle(Order $order): Order
    {
        $payments = Payment::query()->where('order_id', $order->id)->get(['status', 'amount', 'refunded_amount']);
        $paid = $payments->where('status', PaymentAttemptStatus::Paid);

        $paidTotal = (int) $paid->sum('amount');
        $refundedTotal = (int) $paid->sum('refunded_amount');
        $net = $paidTotal - $refundedTotal;
        $pending = $payments->contains('status', PaymentAttemptStatus::Pending);

        $order->forceFill([
            'paid_total' => $paidTotal,
            'refunded_total' => $refundedTotal,
            'payment_status' => match (true) {
                $refundedTotal > 0 && $net <= 0 => PaymentStatus::Refunded,
                $net >= $order->total && $net > 0 => PaymentStatus::Paid,
                $net > 0 => $refundedTotal > 0 ? PaymentStatus::PartiallyRefunded : PaymentStatus::PartiallyPaid,
                $pending => PaymentStatus::Pending,
                default => PaymentStatus::Unpaid,
            },
        ])->save();

        return $order;
    }
}
