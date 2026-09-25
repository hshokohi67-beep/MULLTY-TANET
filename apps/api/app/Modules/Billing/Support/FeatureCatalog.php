<?php

namespace App\Modules\Billing\Support;

use App\Support\Entitlements\FeatureLabels;

/**
 * The features a plan can grant. Code-defined because each one is enforced in code (route
 * middleware `feature:<key>`, `EntitlementGate::ensureCanAdd`, or a side effect that is skipped).
 * Plans store a value for each key; add-ons and platform overrides adjust them.
 */
final class FeatureCatalog
{
    /** On/off features. */
    public const SWITCHES = ['online_payments', 'loyalty', 'inventory', 'operations', 'reports', 'stories', 'custom_domain'];

    /** Countable limits (null = unlimited). `monthly_orders` only warns; it never blocks a customer. */
    public const LIMITS = ['branches', 'staff', 'products', 'monthly_orders'];

    /** @return list<array{key: string, label: string, type: string}> */
    public static function all(): array
    {
        return [
            ...array_map(fn (string $k) => ['key' => $k, 'label' => FeatureLabels::of($k), 'type' => 'switch'], self::SWITCHES),
            ...array_map(fn (string $k) => ['key' => $k, 'label' => FeatureLabels::of($k), 'type' => 'limit'], self::LIMITS),
        ];
    }

    public static function isSwitch(string $key): bool
    {
        return in_array($key, self::SWITCHES, true);
    }

    public static function exists(string $key): bool
    {
        return self::isSwitch($key) || in_array($key, self::LIMITS, true);
    }
}
