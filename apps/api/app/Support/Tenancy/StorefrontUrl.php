<?php

namespace App\Support\Tenancy;

use App\Modules\Core\Models\Tenant;

/**
 * Absolute storefront URLs of a café: its own subdomain when `tenancy.storefront_subdomains` is on
 * (the web proxy passes "/s/{slug}/…" paths through there), else the shared storefront host.
 */
final class StorefrontUrl
{
    /** @param  string  $path  a storefront path such as "/s/{slug}/pay/{id}" */
    public static function to(Tenant $tenant, string $path): string
    {
        if (config('tenancy.storefront_subdomains')) {
            return sprintf('%s://%s.%s%s', config('tenancy.storefront_scheme'), $tenant->slug, config('tenancy.subdomain_base'), $path);
        }

        return rtrim((string) config('payments.storefront_url'), '/').$path;
    }
}
