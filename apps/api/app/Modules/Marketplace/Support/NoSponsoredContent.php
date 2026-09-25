<?php

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Contracts\SponsoredContent;

/** The default when no advertising module is installed: nothing is sponsored. */
final class NoSponsoredContent implements SponsoredContent
{
    public function banners(?string $city): array
    {
        return [];
    }

    public function sponsored(array $storeSlugs, ?string $city, int $limit): array
    {
        return [];
    }
}
