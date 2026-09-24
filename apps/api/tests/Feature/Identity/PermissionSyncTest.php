<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PermissionSyncTest extends TestCase
{
    public function test_new_permissions_reach_existing_tenants_system_roles_without_touching_custom_roles(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithOwner('cafe-a');

        // Simulate a tenant created before the catalog module existed.
        $custom = $this->inTenant($tenant, fn () => Role::query()->create(['key' => 'custom', 'name' => 'سفارشی']));
        Permission::query()->where('key', 'like', 'catalog.%')->delete();

        $this->artisan('permissions:sync')->assertSuccessful();

        $this->inTenant($tenant, function () use ($custom): void {
            $manager = Role::query()->where('key', 'manager')->with('permissions')->firstOrFail();
            $cashier = Role::query()->where('key', 'cashier')->with('permissions')->firstOrFail();

            $this->assertContains('catalog.manage', $manager->permissions->pluck('key'));
            $this->assertContains('catalog.view', $cashier->permissions->pluck('key'));
            $this->assertNotContains('catalog.manage', $cashier->permissions->pluck('key'));
            $this->assertSame(0, DB::table('role_permissions')->where('role_id', $custom->id)->count());
        });

        // Idempotent.
        $this->artisan('permissions:sync')->assertSuccessful();
    }
}
