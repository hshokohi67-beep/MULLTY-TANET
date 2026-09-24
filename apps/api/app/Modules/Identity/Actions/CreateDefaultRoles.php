<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Support\DefaultRoles;

/**
 * Creates the default roles for the tenant in the current context.
 */
final class CreateDefaultRoles
{
    public function __construct(private readonly SyncPermissions $syncPermissions) {}

    /** @return array<string, Role> keyed by role key */
    public function handle(): array
    {
        $this->syncPermissions->handle();
        $permissionIds = Permission::query()->pluck('id', 'key');
        $roles = [];

        foreach (DefaultRoles::definitions() as $key => $definition) {
            $role = Role::query()->create(['key' => $key, 'name' => $definition['name'], 'is_system' => true]);

            if ($definition['permissions'] !== '*') {
                $role->permissions()->sync($permissionIds->only($definition['permissions'])->values()->all());
            }

            $roles[$key] = $role;
        }

        return $roles;
    }
}
