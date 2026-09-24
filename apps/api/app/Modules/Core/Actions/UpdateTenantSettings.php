<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\TenantSetting;
use App\Modules\Core\Support\TenantSettingsRegistry;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateTenantSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $values  already validated, keys must exist in the registry
     */
    public function handle(array $values): void
    {
        DB::transaction(function () use ($values): void {
            $audited = [];

            foreach ($values as $key => $value) {
                if (! TenantSettingsRegistry::has($key)) {
                    throw new InvalidArgumentException("Unknown tenant setting [{$key}].");
                }

                $secret = TenantSettingsRegistry::isSecret($key);
                $setting = TenantSetting::query()->firstOrNew(['key' => $key]);
                $before = $setting->exists ? $setting->plainValue() : null;
                $after = TenantSettingsRegistry::serialize($key, $value);

                if ($setting->exists && $before === $after) {
                    continue;
                }

                $setting->storeValue($after, $secret);
                $setting->save();

                // Secrets are logged only as "changed", never with a value.
                $audited[$key] = $secret ? AuditLogger::REDACTED : ['from' => $before, 'to' => $after];
            }

            if ($audited !== []) {
                $this->audit->record('settings.updated', null, $audited);
            }
        });
    }
}
