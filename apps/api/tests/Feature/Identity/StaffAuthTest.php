<?php

namespace Tests\Feature\Identity;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Support\Tenancy\TenantContext;
use Tests\TestCase;

final class StaffAuthTest extends TestCase
{
    public function test_login_with_persian_digit_mobile_returns_a_staff_token(): void
    {
        $this->createTenantWithOwner('cafe-a', '+989121112233');

        $response = $this->postJson('/api/v1/auth/staff/login', ['identifier' => '۰۹۱۲۱۱۱۲۲۳۳', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'phone']])
            ->assertJsonPath('user.phone', '09121112233');

        $this->getJson('/api/v1/auth/staff/me', ['Authorization' => 'Bearer '.$response->json('token')])
            ->assertOk()
            ->assertJsonPath('memberships.0.tenant.slug', 'cafe-a')
            ->assertJsonPath('memberships.0.permissions', fn (array $p) => in_array('team.manage', $p, true));
    }

    public function test_wrong_password_and_unknown_user_get_the_same_persian_error(): void
    {
        $this->createTenantWithOwner('cafe-a', '+989121112233');

        $wrong = $this->postJson('/api/v1/auth/staff/login', ['identifier' => '09121112233', 'password' => 'nope'])->assertUnprocessable();
        $unknown = $this->postJson('/api/v1/auth/staff/login', ['identifier' => '09129999999', 'password' => 'nope'])->assertUnprocessable();

        $this->assertSame('نام کاربری یا رمز عبور صحیح نیست.', $wrong->json('message'));
        $this->assertSame($wrong->json(), $unknown->json());
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/staff/login', ['identifier' => 'a@example.test', 'password' => 'x']);
        }

        $this->postJson('/api/v1/auth/staff/login', ['identifier' => 'a@example.test', 'password' => 'x'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests');
    }

    public function test_logout_revokes_the_token(): void
    {
        ['owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $headers = ['Authorization' => 'Bearer '.$this->staffToken($owner)];

        $this->postJson('/api/v1/auth/staff/logout', [], $headers)->assertNoContent();
        $this->getJson('/api/v1/auth/staff/me', $headers)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_customer_tokens_cannot_reach_staff_endpoints(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithOwner('cafe-a');
        $customer = $this->inTenant($tenant, fn () => Customer::factory()->create());
        $token = $customer->createToken('t', ['customer'])->plainTextToken;

        // Without a tenant context a customer token doesn't even authenticate.
        $this->getJson('/api/v1/auth/staff/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();

        // With the right tenant it authenticates, but is not a staff actor.
        $this->getJson('/api/v1/branches', ['Authorization' => 'Bearer '.$token, 'X-Tenant' => 'cafe-a'])
            ->assertForbidden()
            ->assertJsonPath('message', 'دسترسی شما برای انجام این عملیات کافی نیست.');
    }

    public function test_login_is_audited_without_secrets(): void
    {
        $this->createTenantWithOwner('cafe-a', '+989121112233');
        $this->postJson('/api/v1/auth/staff/login', ['identifier' => '09121112233', 'password' => 'password'])->assertOk();

        $entry = app(TenantContext::class)->bypass(fn () => AuditLog::query()->where('action', 'staff.logged_in')->first());
        $this->assertNotNull($entry);
        $this->assertNull($entry->tenant_id);
        $this->assertStringNotContainsString('password', (string) json_encode($entry->changes));
    }
}
