<?php

namespace App\Modules\Catalog\Contracts;

use Carbon\CarbonImmutable;

/**
 * Discounts the café shows on its menu cards (menu offers). Catalog can't read discounts (a
 * sibling module), so Commerce binds the real feed; without it the menu simply has no offers.
 */
interface MenuOfferFeed
{
    /**
     * Offers of the branch that are on now or later in the next days; windows are UTC instants.
     *
     * @return list<array{id: string, label: string, kind: string, value: int, priority: int, product_ids: list<string>, category_ids: list<string>, windows: list<array{from: ?string, to: ?string}>}>
     */
    public function forBranch(string $branchId, CarbonImmutable $now): array;
}
