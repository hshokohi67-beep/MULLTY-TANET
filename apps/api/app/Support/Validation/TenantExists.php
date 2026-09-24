<?php

namespace App\Support\Validation;

use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rules\Exists;

/**
 * `exists` scoped to the current tenant: IDs belonging to another tenant fail validation
 * exactly like IDs that don't exist at all.
 */
final class TenantExists
{
    public static function in(string $table, bool $withoutTrashed = false): Exists
    {
        $rule = (new Exists($table, 'id'))->where('tenant_id', app(TenantContext::class)->id());

        return $withoutTrashed ? $rule->whereNull('deleted_at') : $rule;
    }
}
