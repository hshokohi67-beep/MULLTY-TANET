<?php

namespace App\Modules\Loyalty\Actions;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Exceptions\LoyaltyException;
use Illuminate\Support\Facades\DB;

/**
 * Basic referral (discovery D4): a new customer enters a friend's code once; both are rewarded
 * when the new customer's FIRST order completes (not at sign-up, which the legacy system did
 * and which was trivially abusable with throwaway numbers).
 */
final class Referrals
{
    public const WINDOW_DAYS = 7;

    public function __construct(private readonly PostWalletTransaction $wallet) {}

    public function apply(Customer $customer, string $code): Customer
    {
        if ($customer->referred_by_id !== null) {
            throw LoyaltyException::referralAlreadySet();
        }

        $referrer = Customer::query()->where('referral_code', strtoupper(trim($code)))->first();

        if ($referrer === null || $referrer->id === $customer->id) {
            throw LoyaltyException::referralInvalid();
        }

        $hasCompleted = Order::query()->where('customer_id', $customer->id)->where('status', OrderStatus::Completed)->exists();

        if ($hasCompleted || $customer->created_at->lt(now()->subDays(self::WINDOW_DAYS))) {
            throw LoyaltyException::referralNotAllowed();
        }

        $customer->forceFill(['referred_by_id' => $referrer->id, 'referred_at' => now()])->save();

        return $customer;
    }

    /** Called when an order completes; rewards once, on the referred customer's first completed order. */
    public function rewardOnFirstOrder(Order $order): void
    {
        if ($order->customer_id === null || ! TenantSettings::get('loyalty.enabled')) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();

            if ($customer === null || $customer->referred_by_id === null || $customer->referral_rewarded_at !== null) {
                return;
            }

            $referrer = Customer::query()->find($customer->referred_by_id);
            $refereeReward = (int) TenantSettings::get('loyalty.referral_referee_reward');
            $referrerReward = (int) TenantSettings::get('loyalty.referral_referrer_reward');

            if ($refereeReward > 0) {
                $this->wallet->handle($customer, WalletTransactionType::Referral, $refereeReward, "referral:{$customer->id}:referee", 'هدیه‌ی عضویت با کد معرف', $order->id);
            }

            if ($referrer !== null && $referrerReward > 0) {
                $this->wallet->handle($referrer, WalletTransactionType::Referral, $referrerReward, "referral:{$customer->id}:referrer", 'پاداش معرفی '.($customer->name ?: 'دوست'), $order->id);
            }

            $customer->forceFill(['referral_rewarded_at' => now()])->save();
        });
    }
}
