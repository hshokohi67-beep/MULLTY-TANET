<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Commerce\Actions\Orders\TransitionOrder;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\User;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Models\Payment;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Cash / card-reader / other payments taken at the counter. Idempotent per key, can't exceed
 * what is still owed, and releases an order that was waiting for an online payment.
 */
final class RecordStaffPayment
{
    public function __construct(
        private readonly SyncOrderPaymentStatus $sync,
        private readonly TransitionOrder $transition,
    ) {}

    public function handle(Order $order, PaymentMethod $method, ?int $amount, string $idempotencyKey, User $actor, ?string $reference = null, ?string $note = null): Payment
    {
        $key = 'staff:'.$idempotencyKey;

        if ($existing = Payment::query()->where('idempotency_key', $key)->first()) {
            return $existing->order_id === $order->id ? $existing : throw PaymentException::idempotencyConflict();
        }

        try {
            [$payment, $released] = DB::transaction(function () use ($order, $method, $amount, $key, $actor, $reference, $note): array {
                /** @var Order $locked */
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if (in_array($locked->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                    throw PaymentException::orderClosed();
                }

                $remaining = $locked->remainingDue();

                if ($remaining <= 0) {
                    throw PaymentException::nothingDue();
                }

                $amount ??= $remaining;

                if ($amount > $remaining) {
                    throw PaymentException::exceedsRemaining(MoneyFormatter::format(Money::rials($remaining)));
                }

                $payment = Payment::query()->create([
                    'order_id' => $locked->id,
                    'method' => $method,
                    'status' => PaymentAttemptStatus::Paid,
                    'amount' => $amount,
                    'idempotency_key' => $key,
                    'reference' => $reference,
                    'note' => $note,
                    'recorded_by' => $actor->id,
                    'paid_at' => now(),
                ]);
                $this->sync->handle($locked);

                // The customer chose online but paid at the counter instead: send the order to the kitchen.
                $released = $locked->status === OrderStatus::PendingPayment;
                if ($released) {
                    $this->transition->handle($locked, OrderStatus::Placed, 'user', $actor->id, 'پرداخت در صندوق ثبت شد');
                }

                app(AuditLogger::class)->record('payment.recorded', $payment, ['method' => $method->value, 'amount' => $amount, 'order_id' => $locked->id], $actor);

                return [$payment, $released];
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race.
            return Payment::query()->where('idempotency_key', $key)->firstOrFail();
        }

        if ($released) {
            $fresh = Order::query()->findOrFail($order->id);
            DB::afterCommit(fn () => OrderPlaced::dispatch($fresh));
        }

        return $payment;
    }
}
