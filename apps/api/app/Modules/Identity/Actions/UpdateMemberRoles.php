<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\TeamRuleException;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionResolver;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class UpdateMemberRoles
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionResolver $permissions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $roleIds  must belong to the current tenant (validated upstream, re-checked here)
     */
    public function handle(User $actor, TenantUser $member, array $roleIds, bool $audit = true): TenantUser
    {
        return DB::transaction(function () use ($actor, $member, $roleIds, $audit): TenantUser {
            $tenant = $this->context->require();
            // Tenant-scoped query: foreign role IDs simply don't match.
            $roles = Role::query()->whereKey($roleIds)->get();
            $ownerRole = Role::query()->where('key', Role::OWNER)->firstOrFail();

            $wasOwner = $member->roles()->whereKey($ownerRole->getKey())->exists();
            $willBeOwner = $roles->contains(fn (Role $role) => $role->is($ownerRole));
            $actorIsOwner = $this->permissions->membership($actor, $tenant)?->roles->contains(fn (Role $role) => $role->isOwner()) ?? false;

            if ($wasOwner !== $willBeOwner && ! $actorIsOwner) {
                throw TeamRuleException::onlyOwnerAssignsOwner();
            }

            if ($wasOwner && ! $willBeOwner) {
                $otherOwners = TenantUser::query()
                    ->whereKeyNot($member->getKey())
                    ->where('status', TenantUser::STATUS_ACTIVE)
                    ->whereHas('roles', fn ($q) => $q->whereKey($ownerRole->getKey()))
                    ->lockForUpdate()
                    ->count();

                if ($otherOwners === 0) {
                    throw TeamRuleException::lastOwner();
                }
            }

            $member->roles()->sync($roles->mapWithKeys(fn (Role $role) => [$role->getKey() => ['tenant_id' => $tenant->getKey()]])->all());
            $this->permissions->flush();

            if ($audit) {
                $this->audit->record('team.member_roles_updated', $member, ['role_ids' => $roles->modelKeys()]);
            }

            return $member->load('roles');
        });
    }
}
