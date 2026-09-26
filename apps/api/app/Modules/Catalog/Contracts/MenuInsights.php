<?php

namespace App\Modules\Catalog\Contracts;

/**
 * What sales say about the menu, for the storefront: best sellers and "goes well with" pairs.
 * Catalog can't read sales (Analytics sits above it), so Analytics binds the real answer; without
 * it the menu simply has no badges or suggestions.
 */
interface MenuInsights
{
    /** @return list<string> product ids of the current tenant's best sellers, best first */
    public function popular(): array;

    /** @return array<string, list<string>> product id => the products most often bought with it, best first */
    public function pairs(): array;
}
