<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantUser;
use Tests\TestCase;

final class PermissionsTest extends TestCase
{
    public function test_cashier_can_view_but_not_manage_branches(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $cashier = $this->addMember($tenant, $owner, 'cashier');
        $headers = $this->staffHeaders($cashier, $tenant);

        $this->getJson('/api/v1/branches', $headers)->assertOk();
        $this->postJson('/api/v1/branches', ['name' => 'جدید', 'slug' => 'new'], $headers)
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
        $this->getJson('/api/v1/team', $headers)->assertForbidden();
        $this->getJson('/api/v1/tenant/settings', $headers)->assertForbidden();
    }

    public function test_manager_sees_team_but_cannot_change_it(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $manager = $this->addMember($tenant, $owner, 'manager');
        $headers = $this->staffHeaders($manager, $tenant);

        $this->getJson('/api/v1/team', $headers)->assertOk();
        $this->postJson('/api/v1/team', ['name' => 'x', 'phone' => '09350000001', 'role_ids' => ['x']], $headers)->assertForbidden();
        $this->patchJson('/api/v1/tenant/settings', ['settings' => ['contact.phone' => '021']], $headers)->assertForbidden();
    }

    public function test_disabled_membership_loses_access(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $manager = $this->addMember($tenant, $owner, 'manager');
        $this->inTenant($tenant, fn () => TenantUser::query()->where('user_id', $manager->id)->update(['status' => TenantUser::STATUS_DISABLED]));

        $this->getJson('/api/v1/branches', $this->staffHeaders($manager, $tenant))->assertForbidden()->assertJsonPath('code', 'not_a_member');
    }

    public function test_the_last_owner_cannot_be_demoted(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        [$membership, $managerRole] = $this->inTenant($tenant, fn () => [
            TenantUser::query()->where('user_id', $owner->id)->firstOrFail(),
            Role::query()->where('key', 'manager')->firstOrFail(),
        ]);

        $this->putJson("/api/v1/team/{$membership->id}/roles", ['role_ids' => [$managerRole->id]], $this->staffHeaders($owner, $tenant))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'last_owner');
    }

    public function test_only_an_owner_can_grant_the_owner_role(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $admin = $this->addMember($tenant, $owner, 'manager');
        $cashier = $this->addMember($tenant, $owner, 'cashier');

        // Give the manager team.manage through a custom role, to prove the owner rule is separate from permissions.
        [$ownerRole, $cashierMembership] = $this->inTenant($tenant, function () use ($admin, $cashier) {
            $teamRole = Role::query()->create(['key' => 'team-admin', 'name' => 'مدیر تیم']);
            $teamRole->permissions()->sync(Permission::query()->whereIn('key', ['team.view', 'team.manage'])->pluck('id'));
            TenantUser::query()->where('user_id', $admin->id)->firstOrFail()->roles()->attach($teamRole->id, ['tenant_id' => $teamRole->tenant_id]);

            return [Role::query()->where('key', 'owner')->firstOrFail(), TenantUser::query()->where('user_id', $cashier->id)->firstOrFail()];
        });

        $this->putJson("/api/v1/team/{$cashierMembership->id}/roles", ['role_ids' => [$ownerRole->id]], $this->staffHeaders($admin, $tenant))
            ->assertForbidden()
            ->assertJsonPath('code', 'owner_only');

        $this->putJson("/api/v1/team/{$cashierMembership->id}/roles", ['role_ids' => [$ownerRole->id]], $this->staffHeaders($owner, $tenant))
            ->assertOk()
            ->assertJsonPath('data.roles.0.key', 'owner');
    }

    public function test_owner_adds_a_member_by_mobile_number(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $cashierRole = $this->inTenant($tenant, fn () => Role::query()->where('key', 'cashier')->firstOrFail());

        $this->postJson('/api/v1/team', [
            'name' => 'سارا صندوق‌دار',
            'phone' => '۰۹۳۵۱۲۳۴۵۶۷',
            'password' => 'secret-pass-1',
            'role_ids' => [$cashierRole->id],
        ], $this->staffHeaders($owner, $tenant))
            ->assertCreated()
            ->assertJsonPath('data.user.phone', '09351234567')
            ->assertJsonPath('data.roles.0.key', 'cashier');

        $this->postJson('/api/v1/auth/staff/login', ['identifier' => '09351234567', 'password' => 'secret-pass-1'])->assertOk();
    }
}
