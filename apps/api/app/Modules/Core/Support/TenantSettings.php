<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\TenantSetting;

/**
 * Typed read access to the current tenant's settings (defaults from the registry).
 */
final class TenantSettings
{
    public static function get(string $key): mixed
    {
        $setting = TenantSetting::query()->where('key', $key)->first();

        return TenantSettingsRegistry::cast($key, $setting?->plainValue());
    }
}
