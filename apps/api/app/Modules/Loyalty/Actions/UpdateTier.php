<?php

namespace App\Modules\Loyalty\Actions;

use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTier;

/**
 * Moves an account up to the highest tier its lifetime spend reached. Never moves it down
 * automatically (legacy rule; staff can still change a tier by hand).
 * Call with the account row locked.
 */
final class UpdateTier
{
    public function handle(LoyaltyAccount $account): LoyaltyAccount
    {
        $reached = LoyaltyTier::query()->where('min_spend', '<=', $account->lifetime_spend)->orderByDesc('min_spend')->first();

        if ($reached === null || $reached->id === $account->tier_id) {
            return $account;
        }

        $current = $account->tier_id ? LoyaltyTier::query()->find($account->tier_id) : null;

        if ($current === null || $reached->min_spend > $current->min_spend) {
            $account->forceFill(['tier_id' => $reached->id, 'tier_since' => now()])->save();
        }

        return $account;
    }
}
