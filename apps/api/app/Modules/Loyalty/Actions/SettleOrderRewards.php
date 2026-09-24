<?php

namespace App\Modules\Loyalty\Actions;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Enums\PointsTransactionType;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyOrderAward;
use App\Modules\Loyalty\Support\RewardCalculator;
use App\Support\Localization\PersianNumber;
use Illuminate\Support\Facades\DB;

/**
 * Brings an order's rewards in the ledgers to what the order deserves *now*.
 *
 * On first completion the full award is computed with the rules of that moment and frozen.
 * Afterwards (refunds) the targets are the full award scaled by rewardable-now / base, and only the
 * difference is posted, so the action is idempotent and can run any number of times.
 * Clawbacks may take balances below zero; a negative wallet simply can't pay until it is covered.
 */
final class SettleOrderRewards
{
    public function __construct(
        private readonly RewardCalculator $calculator,
        private readonly PostWalletTransaction $wallet,
        private readonly PostPointsTransaction $points,
        private readonly UpdateTier $tiers,
    ) {}

    public function handle(Order $order): ?LoyaltyOrderAward
    {
        if ($order->customer_id === null) {
            return null;
        }

        return DB::transaction(function () use ($order): ?LoyaltyOrderAward {
            $order = Order::query()->with('items')->findOrFail($order->id);
            $customer = Customer::query()->findOrFail($order->customer_id);
            $award = LoyaltyOrderAward::query()->where('order_id', $order->id)->lockForUpdate()->first();

            if ($award === null) {
                if ($order->status !== OrderStatus::Completed || ! TenantSettings::get('loyalty.enabled')) {
                    return null;
                }

                $award = $this->freeze($order, $customer);
            }

            $current = $this->calculator->rewardableAmount($order);
            $ratio = fn (int $full): int => $award->base_amount > 0 ? intdiv($full * min($current, $award->base_amount), $award->base_amount) : 0;

            $targetPoints = $ratio($award->points_full);
            $targetCashback = $ratio($award->cashback_full);
            $targetSpend = min($current, $award->base_amount);

            if ($targetPoints === $award->points_posted && $targetCashback === $award->cashback_posted && $targetSpend === $award->spend_posted) {
                return $award;
            }

            $version = $award->version + 1;
            $label = PersianNumber::toPersian('#'.$order->daily_number);

            if (($delta = $targetPoints - $award->points_posted) !== 0) {
                $this->points->handle(
                    $customer,
                    $delta > 0 ? PointsTransactionType::Earn : PointsTransactionType::EarnReversal,
                    $delta,
                    idempotencyKey: "award:{$order->id}:points:v{$version}",
                    description: ($delta > 0 ? 'امتیاز سفارش ' : 'برگشت امتیاز سفارش ').$label,
                    orderId: $order->id,
                    allowNegative: true,
                );
            }

            if (($delta = $targetCashback - $award->cashback_posted) !== 0) {
                $this->wallet->handle(
                    $customer,
                    $delta > 0 ? WalletTransactionType::Cashback : WalletTransactionType::CashbackReversal,
                    $delta,
                    idempotencyKey: "award:{$order->id}:cashback:v{$version}",
                    description: ($delta > 0 ? 'کش‌بک سفارش ' : 'برگشت کش‌بک سفارش ').$label,
                    orderId: $order->id,
                    allowNegative: true,
                );
            }

            if (($delta = $targetSpend - $award->spend_posted) !== 0) {
                $account = LoyaltyAccount::query()->whereKey(LoyaltyAccount::for($customer)->id)->lockForUpdate()->firstOrFail();
                $account->forceFill(['lifetime_spend' => $account->lifetime_spend + $delta])->save();
                $this->tiers->handle($account);
            }

            $award->forceFill([
                'points_posted' => $targetPoints,
                'cashback_posted' => $targetCashback,
                'spend_posted' => $targetSpend,
                'version' => $version,
            ])->save();

            return $award;
        });
    }

    private function freeze(Order $order, Customer $customer): LoyaltyOrderAward
    {
        $base = $this->calculator->rewardableAmount($order);
        $tier = LoyaltyAccount::for($customer)->tier;
        $cashback = $this->calculator->cashback($order, $base);

        return LoyaltyOrderAward::query()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'base_amount' => $base,
            'points_full' => $this->calculator->points($base, $tier),
            'cashback_full' => $cashback['total'],
            'snapshot' => [
                'tier' => $tier ? ['id' => $tier->id, 'name' => $tier->name, 'multiplier' => $tier->points_multiplier] : null,
                'points_per_100k' => (int) TenantSettings::get('loyalty.points_per_100k'),
                'cashback_rules' => $cashback['rules'],
            ],
        ]);
    }
}
