<?php

namespace App\Modules\Discounts\Support;

use App\Modules\Discounts\Contracts\CustomerTierLookup;

final class NoCustomerTiers implements CustomerTierLookup
{
    public function tierIdFor(?string $customerId): ?string
    {
        return null;
    }
}
