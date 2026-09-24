<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Tests\TestCase;

final class PlatformTenantTest extends TestCase
{
    public function test_platform_admin_provisions_a_complete_tenant(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->postJson('/api/v1/platform/tenants', [
            'name' => 'کافه نو',
            'slug' => 'Cafe-No',
            'owner_name' => 'مریم',
            'owner_phone' => '09120001122',
            'owner_password' => 'strong-pass-1',
        ], $this->staffHeaders($admin))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'cafe-no')
            ->assertJsonPath('data.status', 'trial')
            ->assertJsonPath('data.timezone', 'Asia/Tehran');

        $tenant = Tenant::query()->where('slug', 'cafe-no')->firstOrFail();
        $this->inTenant($tenant, function () {
            $this->assertSame(1, Branch::query()->count());
            $this->assertSame(['cashier', 'kitchen', 'manager', 'owner', 'waiter'], Role::query()->orderBy('key')->pluck('key')->all());
        });

        $this->postJson('/api/v1/auth/staff/login', ['identifier' => '09120001122', 'password' => 'strong-pass-1'])->assertOk();
    }

    public function test_reserved_and_duplicate_slugs_are_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->createTenantWithOwner('taken');
        $payload = ['name' => 'x', 'owner_name' => 'x', 'owner_phone' => '09120001122', 'owner_password' => 'strong-pass-1'];

        $this->postJson('/api/v1/platform/tenants', [...$payload, 'slug' => 'admin'], $this->staffHeaders($admin))->assertJsonValidationErrors('slug');
        $this->postJson('/api/v1/platform/tenants', [...$payload, 'slug' => 'taken'], $this->staffHeaders($admin))->assertJsonValidationErrors('slug');
    }

    public function test_tenant_owners_are_not_platform_admins(): void
    {
        ['owner' => $owner] = $this->createTenantWithOwner('cafe-a');

        $this->getJson('/api/v1/platform/tenants', $this->staffHeaders($owner))->assertForbidden();
        $this->getJson('/api/v1/platform/tenants')->assertUnauthorized();
    }
}
