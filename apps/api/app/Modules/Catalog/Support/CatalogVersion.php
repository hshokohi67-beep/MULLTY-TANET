<?php

namespace App\Modules\Catalog\Support;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * A per-tenant version stamp for the catalog. Every catalog write bumps it, and
 * cached public menus are keyed by it, so a stale menu is never served after a change.
 */
final class CatalogVersion
{
    public static function current(string $tenantId): string
    {
        return (string) Cache::rememberForever(self::key($tenantId), fn () => (string) microtime(true));
    }

    public static function bump(): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId !== null) {
            Cache::forever(self::key($tenantId), (string) microtime(true).random_int(0, 999));
        }
    }

    private static function key(string $tenantId): string
    {
        return 'catalog:version:'.$tenantId;
    }
}
