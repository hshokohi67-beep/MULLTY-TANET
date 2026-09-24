<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\TenantBranding;
use App\Support\Audit\AuditLogger;

final class UpdateBranding
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): TenantBranding
    {
        $branding = TenantBranding::query()->firstOrNew();
        $branding->fill($attributes);
        $changes = $branding->getDirty();
        $branding->save();

        if ($changes !== []) {
            $this->audit->record('branding.updated', $branding, $changes);
        }

        return $branding;
    }
}
