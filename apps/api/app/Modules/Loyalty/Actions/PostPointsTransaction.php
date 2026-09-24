<?php

namespace App\Modules\Loyalty\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Enums\PointsTransactionType;
use App\Modules\Loyalty\Exceptions\LoyaltyException;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY way a points balance changes. Same guarantees as {@see PostWalletTransaction}.
 */
final class PostPointsTransaction
{
    public function handle(
        Customer $customer,
        PointsTransactionType $type,
        int $points,
        ?string $idempotencyKey = null,
        ?string $description = null,
        ?string $orderId = null,
        ?string $actorType = 'system',
        ?string $actorId = null,
        bool $allowNegative = false,
    ): LoyaltyTransaction {
        if ($idempotencyKey !== null && ($existing = LoyaltyTransaction::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        $account = LoyaltyAccount::for($customer);

        try {
            return DB::transaction(function () use ($account, $type, $points, $idempotencyKey, $description, $orderId, $actorType, $actorId, $allowNegative): LoyaltyTransaction {
                /** @var LoyaltyAccount $locked */
                $locked = LoyaltyAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
                $balance = $locked->points + $points;

                if ($points < 0 && $balance < 0 && ! $allowNegative) {
                    throw LoyaltyException::insufficientPoints();
                }

                $transaction = LoyaltyTransaction::query()->create([
                    'account_id' => $locked->id,
                    'type' => $type,
                    'points' => $points,
                    'balance_after' => $balance,
                    'order_id' => $orderId,
                    'description' => $description,
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $locked->forceFill(['points' => $balance])->save();

                return $transaction;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $idempotencyKey !== null ? LoyaltyTransaction::query()->where('idempotency_key', $idempotencyKey)->firstOrFail() : throw $e;
        }
    }
}
