<?php

namespace App\Modules\Loyalty\Support;

use App\Modules\Core\Support\TenantSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Modules\Loyalty\Models\Wallet;

/** One customer's club state, as shown to the customer and to staff. Amounts are integer rial. */
final class ClubSummary
{
    /** @return array<string, mixed> */
    public static function for(Customer $customer): array
    {
        $wallet = Wallet::query()->where('customer_id', $customer->id)->first();
        $account = LoyaltyAccount::query()->with('tier')->where('customer_id', $customer->id)->first();
        $spend = $account->lifetime_spend ?? 0;
        $tier = $account?->tier;
        $next = LoyaltyTier::query()
            ->where('min_spend', '>', max($spend, $tier->min_spend ?? -1))
            ->orderBy('min_spend')
            ->first();
        $pointValue = (int) TenantSettings::get('loyalty.point_value');
        $points = $account->points ?? 0;

        return [
            'wallet_balance' => $wallet->balance ?? 0,
            'points' => $points,
            'points_value' => max(0, $points) * $pointValue,
            'lifetime_spend' => $spend,
            'tier' => $tier ? self::tier($tier) : null,
            'next_tier' => $next ? [...self::tier($next), 'remaining' => max(0, $next->min_spend - $spend)] : null,
            'referral_code' => $customer->ensureReferralCode(),
            'program' => [
                'enabled' => (bool) TenantSettings::get('loyalty.enabled'),
                'wallet_payments' => (bool) TenantSettings::get('wallet.payments_enabled'),
                'points_per_100k' => (int) TenantSettings::get('loyalty.points_per_100k'),
                'point_value' => $pointValue,
                'min_redeem_points' => (int) TenantSettings::get('loyalty.min_redeem_points'),
                'birthday_wallet_gift' => (int) TenantSettings::get('loyalty.birthday_wallet_gift'),
                'birthday_points' => (int) TenantSettings::get('loyalty.birthday_points'),
                'referral_referrer_reward' => (int) TenantSettings::get('loyalty.referral_referrer_reward'),
                'referral_referee_reward' => (int) TenantSettings::get('loyalty.referral_referee_reward'),
            ],
        ];
    }

    /** @return array{id: string, name: string, color: string, min_spend: int, points_multiplier: int, perks: ?string} */
    public static function tier(LoyaltyTier $tier): array
    {
        return [
            'id' => $tier->id,
            'name' => $tier->name,
            'color' => $tier->color,
            'min_spend' => $tier->min_spend,
            'points_multiplier' => $tier->points_multiplier,
            'perks' => $tier->perks,
        ];
    }
}
