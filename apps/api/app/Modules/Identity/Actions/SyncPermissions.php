<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the code-defined catalogue into the permissions table. Idempotent.
 *
 * When a module adds permissions, existing tenants' *system* roles receive the new
 * defaults (additive only; nothing a tenant removed or customised is taken away twice,
 * and custom roles are never touched). Owners need no rows: they hold every key.
 */
final class SyncPermissions
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(): void
    {
        $existing = Permission::query()->pluck('key')->all();

        foreach (PermissionCatalog::all() as $key => $definition) {
            Permission::query()->updateOrCreate(['key' => $key], ['group' => $definition['group']]);
        }

        Permission::query()->whereNotIn('key', PermissionCatalog::keys())->delete();

        $newKeys = array_values(array_diff(PermissionCatalog::keys(), $existing));

        if ($existing !== [] && $newKeys !== []) {
            $this->grantNewDefaults($newKeys);
        }
    }

    /**
     * @param  list<string>  $newKeys
     */
    private function grantNewDefaults(array $newKeys): void
    {
        $permissionIds = Permission::query()->whereIn('key', $newKeys)->pluck('id', 'key');

        // Platform-level maintenance across all tenants' system roles.
        $this->context->bypass(function () use ($permissionIds): void {
            foreach (DefaultRoles::definitions() as $roleKey => $definition) {
                if ($definition['permissions'] === '*') {
                    continue;
                }

                $grant = $permissionIds->only($definition['permissions'])->values()->all();

                if ($grant === []) {
                    continue;
                }

                Role::query()->where('key', $roleKey)->where('is_system', true)->pluck('id')
                    ->each(function (string $roleId) use ($grant): void {
                        foreach ($grant as $permissionId) {
                            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
                        }
                    });
            }
        });
    }
}
