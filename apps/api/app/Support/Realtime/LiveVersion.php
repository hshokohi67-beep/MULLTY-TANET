<?php

namespace App\Support\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A cheap "has anything changed?" counter per live view (the kitchen board of a branch, a
 * tenant's order board). Writers bump it after commit; pollers compare it with what they last
 * saw and get a 304 without touching the database when nothing changed.
 *
 * Losing the cache is harmless: an unknown version is a fresh random one, so screens simply
 * refetch once.
 */
final class LiveVersion
{
    private const TTL_SECONDS = 86_400;

    public static function kitchen(string $tenantId, string $branchId): string
    {
        return "live:{$tenantId}:kitchen:{$branchId}";
    }

    public static function orders(string $tenantId): string
    {
        return "live:{$tenantId}:orders";
    }

    public static function get(string $key): string
    {
        return (string) Cache::remember($key, self::TTL_SECONDS, fn () => bin2hex(random_bytes(6)));
    }

    /** Bump after the surrounding transaction commits (readers must not see a version before the data). */
    public static function bump(string ...$keys): void
    {
        DB::afterCommit(function () use ($keys): void {
            foreach ($keys as $key) {
                Cache::put($key, bin2hex(random_bytes(6)), self::TTL_SECONDS);
            }
        });
    }
}
