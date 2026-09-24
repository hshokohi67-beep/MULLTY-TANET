<?php

namespace App\Modules\Loyalty\Support;

use App\Modules\Discounts\Contracts\CustomerTierLookup;
use App\Modules\Loyalty\Models\LoyaltyAccount;

final class LoyaltyTierLookup implements CustomerTierLookup
{
    public function tierIdFor(?string $customerId): ?string
    {
        if ($customerId === null) {
            return null;
        }

        $tier = LoyaltyAccount::query()->where('customer_id', $customerId)->value('tier_id');

        return is_string($tier) ? $tier : null;
    }
}
