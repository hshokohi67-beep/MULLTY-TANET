<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Tenant;
use App\Support\Audit\AuditLogger;

final class UpdateTenantProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string, timezone?: string, display_currency_unit?: string}  $attributes
     */
    public function handle(Tenant $tenant, array $attributes): Tenant
    {
        $tenant->fill($attributes);
        $changes = $tenant->getDirty();
        $tenant->save();

        if ($changes !== []) {
            $this->audit->record('tenant.updated', $tenant, $changes);
        }

        return $tenant;
    }
}
