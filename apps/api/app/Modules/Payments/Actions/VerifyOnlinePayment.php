<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Commerce\Actions\Orders\TransitionOrder;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Models\Order;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\GatewayFactory;
use App\Modules\Payments\Support\PaymentLog;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Confirms an online payment with the gateway, server to server, using the amount WE stored.
 * Safe to call any number of times (callback, page refresh, reconcile job): a paid payment
 * is returned as is, and concurrent calls are serialised by a lock.
 *
 * On success an order waiting for payment is released to the kitchen (pending_payment → placed).
 * A network error leaves the payment pending for the reconcile job; it is never marked failed.
 */
final class VerifyOnlinePayment
{
    public function __construct(
        private readonly GatewayFactory $gateways,
        private readonly SyncOrderPaymentStatus $sync,
        private readonly TransitionOrder $transition,
        private readonly PaymentLog $log,
    ) {}

    /**
     * @param  PaymentAttemptStatus  $onFailure  Failed for a customer callback, Expired when the reconcile job gives up
     */
    public function handle(Payment $payment, ?string $authority, string $action = 'verify', PaymentAttemptStatus $onFailure = PaymentAttemptStatus::Failed): Payment
    {
        if ($payment->method !== PaymentMethod::Online || $payment->authority === null || ! is_string($authority) || ! hash_equals($payment->authority, $authority)) {
            throw PaymentException::notFound();
        }

        if ($payment->status === PaymentAttemptStatus::Paid) {
            return $payment;
        }

        try {
            return Cache::lock('payment-verify:'.$payment->id, 30)->block(15, fn () => $this->verify($payment, $action, $onFailure));
        } catch (LockTimeoutException) {
            throw PaymentException::inProgress();
        }
    }

    private function verify(Payment $payment, string $action, PaymentAttemptStatus $onFailure): Payment
    {
        $payment->refresh();

        if ($payment->status === PaymentAttemptStatus::Paid) {
            return $payment;
        }

        $result = $this->gateways->make((string) $payment->gateway)->verify($payment->amount, (string) $payment->authority);
        $this->log->write($payment, $action, $result);

        if ($result->transient) {
            return $payment;
        }

        $released = DB::transaction(function () use ($payment, $result, $onFailure): bool {
            /** @var Order $order */
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if (! $result->success) {
                $payment->forceFill(['status' => $onFailure, 'failure_code' => $result->code, 'failed_at' => now()])->save();
                $this->sync->handle($order);

                return false;
            }

            $payment->forceFill([
                'status' => PaymentAttemptStatus::Paid,
                'ref_id' => $result->refId,
                'card_pan' => $result->cardPan,
                'fee' => $result->fee,
                'paid_at' => now(),
                'failure_code' => null,
                'failed_at' => null,
            ])->save();
            $this->sync->handle($order);

            // A payment that lands after the order was cancelled is still recorded (the money was
            // taken); the order then shows "needs refund" instead of being revived.
            if ($order->status !== OrderStatus::PendingPayment) {
                return false;
            }

            $this->transition->handle($order, OrderStatus::Placed, 'system', null, 'پرداخت اینترنتی تأیید شد');

            return true;
        });

        if ($released) {
            $order = Order::query()->findOrFail($payment->order_id);
            DB::afterCommit(fn () => OrderPlaced::dispatch($order));
        }

        return $payment->refresh();
    }
}
