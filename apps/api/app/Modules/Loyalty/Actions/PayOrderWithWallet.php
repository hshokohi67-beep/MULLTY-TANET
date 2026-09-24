<?php

namespace App\Modules\Loyalty\Actions;

use App\Modules\Commerce\Actions\Orders\TransitionOrder;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Exceptions\LoyaltyException;
use App\Modules\Loyalty\Models\Wallet;
use App\Modules\Payments\Actions\SyncOrderPaymentStatus;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Models\Payment;
use App\Support\Localization\PersianNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Pays (part of) an order from the customer's club wallet: min(balance, remaining, requested).
 * The payment row and the wallet debit are one transaction. If that settles an order waiting for
 * an online payment, the order goes to the kitchen; the rest can still be paid online or at the counter.
 * Lock order: order → wallet (the same order as refunds).
 */
final class PayOrderWithWallet
{
    public function __construct(
        private readonly PostWalletTransaction $wallet,
        private readonly SyncOrderPaymentStatus $sync,
        private readonly TransitionOrder $transition,
    ) {}

    public function handle(Order $order, ?int $maxAmount = null, ?string $idempotencyKey = null, string $actorType = 'customer', ?string $actorId = null): Payment
    {
        if (! TenantSettings::get('wallet.payments_enabled')) {
            throw LoyaltyException::walletPaymentsDisabled();
        }

        if ($idempotencyKey !== null && ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing->order_id === $order->id ? $existing : throw LoyaltyException::idempotencyConflict();
        }

        try {
            [$payment, $released] = DB::transaction(function () use ($order, $maxAmount, $idempotencyKey, $actorType, $actorId): array {
                /** @var Order $locked */
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $customer = $locked->customer_id ? Customer::query()->find($locked->customer_id) : null;

                if ($customer === null) {
                    throw LoyaltyException::orderHasNoCustomer();
                }

                if (in_array($locked->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                    throw PaymentException::orderClosed();
                }

                $remaining = $locked->remainingDue();
                if ($remaining <= 0) {
                    throw PaymentException::nothingDue();
                }

                $balance = Wallet::for($customer)->balance;
                if ($balance <= 0) {
                    throw LoyaltyException::walletEmpty();
                }

                $amount = min($remaining, $balance, $maxAmount ?? PHP_INT_MAX);

                $payment = Payment::query()->create([
                    'order_id' => $locked->id,
                    'method' => PaymentMethod::Wallet,
                    'status' => PaymentAttemptStatus::Paid,
                    'amount' => $amount,
                    'idempotency_key' => $idempotencyKey,
                    'recorded_by' => $actorType === 'user' ? $actorId : null,
                    'paid_at' => now(),
                ]);

                // Throws (and rolls everything back) if a concurrent debit emptied the wallet meanwhile.
                $this->wallet->handle(
                    $customer,
                    WalletTransactionType::OrderPayment,
                    -$amount,
                    idempotencyKey: 'wallet-payment:'.$payment->id,
                    description: 'پرداخت سفارش '.PersianNumber::toPersian('#'.$locked->daily_number),
                    orderId: $locked->id,
                    paymentId: $payment->id,
                    actorType: $actorType,
                    actorId: $actorId,
                );

                $this->sync->handle($locked);

                $released = $locked->status === OrderStatus::PendingPayment && $locked->remainingDue() === 0;
                if ($released) {
                    $this->transition->handle($locked, OrderStatus::Placed, $actorType, $actorId, 'پرداخت با کیف پول');
                }

                return [$payment, $released];
            });
        } catch (UniqueConstraintViolationException $e) {
            return $idempotencyKey !== null ? Payment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail() : throw $e;
        }

        if ($released) {
            $fresh = Order::query()->findOrFail($order->id);
            DB::afterCommit(fn () => OrderPlaced::dispatch($fresh));
        }

        return $payment;
    }
}
