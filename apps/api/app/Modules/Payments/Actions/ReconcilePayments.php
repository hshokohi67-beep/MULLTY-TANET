<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Commerce\Actions\Orders\TransitionOrder;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs for the current tenant (the command loops over tenants):
 *  1. open online attempts past their TTL are verified once more (the customer may have paid and
 *     closed the tab); if the gateway says unpaid they expire, if it is unreachable they stay for the next run;
 *  2. orders still waiting for payment after the payment window, with no open attempt left, are cancelled.
 *
 * @phpstan-type Summary array{verified: int, expired: int, cancelled: int, errors: int}
 */
final class ReconcilePayments
{
    public function __construct(
        private readonly VerifyOnlinePayment $verify,
        private readonly SyncOrderPaymentStatus $sync,
        private readonly TransitionOrder $transition,
    ) {}

    /** @return Summary */
    public function handle(): array
    {
        $summary = ['verified' => 0, 'expired' => 0, 'cancelled' => 0, 'errors' => 0];

        $stale = Payment::query()
            ->where('method', PaymentMethod::Online)
            ->where('status', PaymentAttemptStatus::Pending)
            ->where('expires_at', '<=', now())
            ->limit(200)
            ->get();

        foreach ($stale as $payment) {
            try {
                if ($payment->authority === null) {
                    // The gateway never opened a session, so nothing can have been paid.
                    DB::transaction(function () use ($payment): void {
                        $payment->forceFill(['status' => PaymentAttemptStatus::Expired, 'failed_at' => now()])->save();
                        $this->sync->handle(Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail());
                    });
                    $summary['expired']++;

                    continue;
                }

                $result = $this->verify->handle($payment, $payment->authority, 'reconcile', PaymentAttemptStatus::Expired);

                // The gateway has been unreachable for a whole day: stop waiting. A late success can
                // still be recorded by the customer's callback, since verify accepts expired attempts.
                if ($result->status === PaymentAttemptStatus::Pending && $payment->expires_at?->lte(now()->subDay())) {
                    DB::transaction(function () use ($result): void {
                        $result->forceFill(['status' => PaymentAttemptStatus::Expired, 'failure_code' => 'unreachable', 'failed_at' => now()])->save();
                        $this->sync->handle(Order::query()->whereKey($result->order_id)->lockForUpdate()->firstOrFail());
                    });
                }

                match ($result->status) {
                    PaymentAttemptStatus::Paid => $summary['verified']++,
                    PaymentAttemptStatus::Expired, PaymentAttemptStatus::Failed => $summary['expired']++,
                    PaymentAttemptStatus::Pending => null,
                };
            } catch (Throwable $e) {
                report($e);
                $summary['errors']++;
            }
        }

        $abandoned = Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('placed_at', '<=', now()->subMinutes((int) config('payments.order_payment_window_minutes')))
            ->whereNotIn('id', Payment::query()->select('order_id')->where('status', PaymentAttemptStatus::Pending))
            ->limit(200)
            ->get();

        foreach ($abandoned as $order) {
            try {
                $this->transition->handle($order, OrderStatus::Cancelled, 'system', null, 'پرداخت در مهلت مقرر انجام نشد');
                $summary['cancelled']++;
            } catch (Throwable $e) {
                report($e);
                $summary['errors']++;
            }
        }

        return $summary;
    }
}
