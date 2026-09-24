<?php

namespace App\Modules\Loyalty\Listeners;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderCompleted;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Actions\PostWalletTransaction;
use App\Modules\Loyalty\Actions\Referrals;
use App\Modules\Loyalty\Actions\SettleOrderRewards;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Payments\Actions\RecordRefund;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\RefundMethod;
use App\Modules\Payments\Events\PaymentRefunded;
use App\Modules\Payments\Models\Payment;
use App\Support\Localization\PersianNumber;
use Throwable;

/**
 * Connects orders and payments to the club. All handlers are synchronous and idempotent:
 *  - completed order → rewards + referral reward;
 *  - cancelled/rejected order → wallet payments go back to the wallet;
 *  - refund → a wallet payment's refund is credited to the wallet (inside the refund transaction),
 *    and the order's rewards are rescaled.
 */
final class ClubOrderListener
{
    public function completed(OrderCompleted $event): void
    {
        app(SettleOrderRewards::class)->handle($event->order);
        app(Referrals::class)->rewardOnFirstOrder($event->order);
    }

    public function statusChanged(OrderStatusChanged $event): void
    {
        if (! in_array($event->to, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            return;
        }

        $payments = Payment::query()
            ->where('order_id', $event->order->id)
            ->where('method', PaymentMethod::Wallet)
            ->where('status', PaymentAttemptStatus::Paid)
            ->get();

        foreach ($payments as $payment) {
            if ($payment->refundable() <= 0) {
                continue;
            }

            try {
                app(RecordRefund::class)->handle($payment, $payment->refundable(), RefundMethod::Wallet, 'لغو سفارش', 'auto-cancel:'.$payment->id, null);
            } catch (Throwable $e) {
                report($e); // idempotent key: staff can retry from the order page
            }
        }
    }

    public function refunded(PaymentRefunded $event): void
    {
        $payment = $event->payment;

        if ($payment->method === PaymentMethod::Wallet) {
            $customerId = $payment->order()->value('customer_id');
            $customer = is_string($customerId) ? Customer::query()->find($customerId) : null;

            if ($customer !== null) {
                app(PostWalletTransaction::class)->handle(
                    $customer,
                    WalletTransactionType::OrderRefund,
                    $event->refund->amount,
                    idempotencyKey: 'wallet-refund:'.$event->refund->id,
                    description: 'بازگشت وجه سفارش '.PersianNumber::toPersian('#'.$payment->order()->value('daily_number')),
                    orderId: $payment->order_id,
                    paymentId: $payment->id,
                    actorType: $event->refund->actor_id ? 'user' : 'system',
                    actorId: $event->refund->actor_id,
                );
            }
        }

        $order = $payment->order()->first();
        if ($order !== null) {
            app(SettleOrderRewards::class)->handle($order);
        }
    }
}
