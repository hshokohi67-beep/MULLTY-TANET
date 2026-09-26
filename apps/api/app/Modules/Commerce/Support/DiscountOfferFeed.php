<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Catalog\Contracts\MenuOfferFeed;
use App\Modules\Discounts\Support\MenuOffers;
use Carbon\CarbonImmutable;

/** Catalog's menu offers, answered by Discounts (Commerce sits above both). */
final class DiscountOfferFeed implements MenuOfferFeed
{
    public function forBranch(string $branchId, CarbonImmutable $now): array
    {
        return MenuOffers::forBranch($branchId, $now);
    }
}
