<?php

namespace App\Modules\Identity\Support;

use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Resolves what a staff user may do inside one tenant. Memoised per request.
 */
final class PermissionResolver
{
    /** @var array<string, ?TenantUser> */
    private array $memberships = [];

    /** @var array<string, list<string>> */
    private array $permissions = [];

    public function __construct(private readonly TenantContext $context) {}

    public function membership(User $user, Tenant $tenant): ?TenantUser
    {
        $key = $tenant->getKey().':'.$user->getKey();

        return $this->memberships[$key] ??= $this->context->runAs(
            $tenant,
            fn () => TenantUser::query()->with('roles.permissions')->where('user_id', $user->getKey())->first(),
        );
    }

    /** @return list<string> */
    public function permissions(User $user, Tenant $tenant): array
    {
        $key = $tenant->getKey().':'.$user->getKey();

        if (isset($this->permissions[$key])) {
            return $this->permissions[$key];
        }

        $membership = $this->membership($user, $tenant);

        if (! $membership?->isActive() || ! $tenant->canOperate()) {
            return $this->permissions[$key] = [];
        }

        if ($membership->roles->contains(fn ($role) => $role->isOwner())) {
            return $this->permissions[$key] = PermissionCatalog::keys();
        }

        $granted = $membership->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('key'))
            ->unique()
            ->values()
            ->all();

        return $this->permissions[$key] = array_values(array_intersect(PermissionCatalog::keys(), $granted));
    }

    public function allows(User $user, Tenant $tenant, string $permission): bool
    {
        return in_array($permission, $this->permissions($user, $tenant), true);
    }

    public function flush(): void
    {
        $this->memberships = [];
        $this->permissions = [];
    }
}
