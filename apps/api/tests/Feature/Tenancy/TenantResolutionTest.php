<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Core\Enums\TenantStatus;
use Tests\TestCase;

final class TenantResolutionTest extends TestCase
{
    public function test_resolves_by_slug_header(): void
    {
        $this->createTenantWithOwner('cafe-a');

        $this->getJson('/api/v1/public/tenant', ['X-Tenant' => 'cafe-a'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'cafe-a')
            ->assertJsonPath('data.display_currency_unit', 'toman');
    }

    public function test_resolves_by_domain_header_ignoring_port_and_www(): void
    {
        $this->createTenantWithOwner('cafe-a');

        $this->getJson('/api/v1/public/tenant', ['X-Tenant-Domain' => 'www.cafe-a.menu.test:443'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'cafe-a');
    }

    public function test_public_projection_exposes_no_internal_fields(): void
    {
        $this->createTenantWithOwner('cafe-a');

        $data = $this->getJson('/api/v1/public/tenant', ['X-Tenant' => 'cafe-a'])->json('data');

        $this->assertEqualsCanonicalizing(['name', 'slug', 'locale', 'timezone', 'display_currency_unit', 'branding'], array_keys($data));
    }

    public function test_unknown_or_missing_tenant_is_a_persian_404(): void
    {
        $this->getJson('/api/v1/public/tenant', ['X-Tenant' => 'nope'])
            ->assertNotFound()
            ->assertJsonPath('code', 'tenant_not_found')
            ->assertJsonPath('message', 'کسب‌وکار موردنظر پیدا نشد.');

        $this->getJson('/api/v1/public/tenant')->assertNotFound();
    }

    public function test_suspended_tenant_is_blocked_even_for_its_owner(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $tenant->update(['status' => TenantStatus::Suspended]);

        $this->getJson('/api/v1/public/tenant', ['X-Tenant' => 'cafe-a'])->assertForbidden()->assertJsonPath('code', 'tenant_suspended');
        $this->getJson('/api/v1/branches', $this->staffHeaders($owner, $tenant))->assertForbidden();
    }
}
