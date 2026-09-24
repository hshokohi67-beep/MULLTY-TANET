<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\User;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\RefundMethod;
use App\Modules\Payments\Events\PaymentRefunded;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Records money returned to the customer. Online refunds are done in the gateway panel
 * (see phase-04 plan §7) and recorded here with the panel's reference.
 * The refundable amount is checked under a row lock, so two refunds can't exceed the payment.
 * A wallet payment can only be refunded back into the wallet (the Loyalty module credits it by
 * listening to PaymentRefunded, which is dispatched inside the same transaction).
 */
final class RecordRefund
{
    public function __construct(private readonly SyncOrderPaymentStatus $sync) {}

    public function handle(Payment $payment, int $amount, RefundMethod $method, string $reason, string $idempotencyKey, ?User $actor, ?string $reference = null): PaymentRefund
    {
        if ($existing = PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing->payment_id === $payment->id ? $existing : throw PaymentException::idempotencyConflict();
        }

        try {
            return DB::transaction(function () use ($payment, $amount, $method, $reason, $idempotencyKey, $actor, $reference): PaymentRefund {
                // Lock order first, then payment: the same order as every other payment write.
                $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                /** @var Payment $locked */
                $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if ($locked->refundable() <= 0) {
                    throw PaymentException::notRefundable();
                }

                if (($locked->method === PaymentMethod::Wallet) !== ($method === RefundMethod::Wallet)) {
                    throw PaymentException::walletRefundMethod();
                }

                if ($amount > $locked->refundable()) {
                    throw PaymentException::refundExceeds(MoneyFormatter::format(Money::rials($locked->refundable())));
                }

                $refund = PaymentRefund::query()->create([
                    'payment_id' => $locked->id,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'reason' => $reason,
                    'idempotency_key' => $idempotencyKey,
                    'actor_id' => $actor?->id,
                ]);

                $locked->increment('refunded_amount', $amount);
                $this->sync->handle($order);

                PaymentRefunded::dispatch($locked->refresh(), $refund);

                app(AuditLogger::class)->record('payment.refunded', $locked, ['amount' => $amount, 'method' => $method->value, 'reason' => $reason], $actor);

                return $refund;
            });
        } catch (UniqueConstraintViolationException) {
            return PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }
}
