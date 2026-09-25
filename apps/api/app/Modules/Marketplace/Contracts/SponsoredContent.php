<?php

namespace App\Modules\Marketplace\Contracts;

/**
 * Paid placements inside the public marketplace. Marketplace only asks; the Advertising module
 * (above it) answers. Without it nothing is sponsored (NoSponsoredContent).
 */
interface SponsoredContent
{
    /**
     * Banners for the marketplace home, or above the results of one city.
     *
     * @return list<array<string, mixed>>
     */
    public function banners(?string $city): array;

    /**
     * Which of these matching stores (in rank order) to show first as sponsored, with the ad's
     * public extras. Only stores already in the result set can be sponsored.
     *
     * @param  list<string>  $storeSlugs
     * @return list<array{store: string, ad: array<string, mixed>}>
     */
    public function sponsored(array $storeSlugs, ?string $city, int $limit): array;
}
