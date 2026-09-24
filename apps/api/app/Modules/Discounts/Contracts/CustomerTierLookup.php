<?php

namespace App\Modules\Discounts\Contracts;

/**
 * Lets a discount target a loyalty tier without Discounts depending on the Loyalty module.
 * The default binding knows no tiers; the Loyalty module rebinds it.
 */
interface CustomerTierLookup
{
    public function tierIdFor(?string $customerId): ?string;
}
