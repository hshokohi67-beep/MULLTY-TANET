<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Contracts\MenuOfferFeed;
use Carbon\CarbonImmutable;

/** The default until a module that knows about discounts binds MenuOfferFeed. */
final class NoMenuOffers implements MenuOfferFeed
{
    public function forBranch(string $branchId, CarbonImmutable $now): array
    {
        return [];
    }
}
