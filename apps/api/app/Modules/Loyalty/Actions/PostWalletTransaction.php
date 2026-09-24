<?php

namespace App\Modules\Loyalty\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Exceptions\LoyaltyException;
use App\Modules\Loyalty\Models\Wallet;
use App\Modules\Loyalty\Models\WalletTransaction;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY way a wallet balance changes. Locks the wallet row, appends a ledger row with the
 * resulting balance, and updates the balance in the same transaction, so
 * balance = Σ amounts = last balance_after holds at all times.
 *
 * With an idempotency key a repeated call returns the first transaction instead of posting twice.
 * A debit may take the balance below zero only when $allowNegative (reward clawbacks).
 */
final class PostWalletTransaction
{
    public function handle(
        Customer $customer,
        WalletTransactionType $type,
        int $amount,
        ?string $idempotencyKey = null,
        ?string $description = null,
        ?string $orderId = null,
        ?string $paymentId = null,
        ?string $actorType = 'system',
        ?string $actorId = null,
        bool $allowNegative = false,
    ): WalletTransaction {
        if ($idempotencyKey !== null && ($existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        $wallet = Wallet::for($customer);

        try {
            return DB::transaction(function () use ($wallet, $type, $amount, $idempotencyKey, $description, $orderId, $paymentId, $actorType, $actorId, $allowNegative): WalletTransaction {
                /** @var Wallet $locked */
                $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
                $balance = $locked->balance + $amount;

                if ($amount < 0 && $balance < 0 && ! $allowNegative) {
                    throw LoyaltyException::insufficientWallet(MoneyFormatter::format(Money::rials(max(0, $locked->balance))));
                }

                $transaction = WalletTransaction::query()->create([
                    'wallet_id' => $locked->id,
                    'type' => $type,
                    'amount' => $amount,
                    'balance_after' => $balance,
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'description' => $description,
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $locked->forceFill(['balance' => $balance])->save();

                return $transaction;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent call with the same key won.
            return $idempotencyKey !== null ? WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->firstOrFail() : throw $e;
        }
    }
}
