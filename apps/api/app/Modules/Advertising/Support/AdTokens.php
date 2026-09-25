<?php

namespace App\Modules\Advertising\Support;

/**
 * Short-lived, HMAC-signed event tokens handed out with every served ad: impressions and clicks
 * can only be recorded for ads that were actually served in the last couple of hours.
 * Format: "{ref}.{placement}.{expires}.{signature}" (no secrets inside; the signature is the point).
 */
final class AdTokens
{
    public const TTL_SECONDS = 7200;

    public static function make(string $ref, string $placement, ?int $now = null): string
    {
        $payload = sprintf('%s.%s.%d', $ref, $placement, ($now ?? now()->getTimestamp()) + self::TTL_SECONDS);

        return $payload.'.'.self::sign($payload);
    }

    /** @return array{ref: string, placement: string}|null */
    public static function read(string $token, ?int $now = null): ?array
    {
        if (strlen($token) > 120 || preg_match('/^([A-Za-z0-9]{8,24})\.([a-z_]{3,24})\.(\d{9,11})\.([a-f0-9]{32})$/', $token, $m) !== 1) {
            return null;
        }
        [, $ref, $placement, $expires, $signature] = $m;
        if (! hash_equals(self::sign("{$ref}.{$placement}.{$expires}"), $signature) || (int) $expires < ($now ?? now()->getTimestamp())) {
            return null;
        }

        return ['ref' => $ref, 'placement' => $placement];
    }

    private static function sign(string $payload): string
    {
        $key = hash_hmac('sha256', 'ad-events', (string) config('app.key'));

        return substr(hash_hmac('sha256', $payload, $key), 0, 32);
    }
}
