<?php

namespace Tests;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Actions\CreateTenant;
use App\Modules\Core\Data\CreateTenantData;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Actions\AddTeamMember;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Sms\Providers\ArraySmsProvider;
use App\Support\Sms\SmsProvider;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected ArraySmsProvider $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new ArraySmsProvider;
        $this->app->instance(SmsProvider::class, $this->sms);
    }

    /**
     * Provision a tenant exactly like production does, and return it with its owner.
     *
     * @return array{tenant: Tenant, owner: User}
     */
    protected function createTenantWithOwner(string $slug = 'cafe-a', ?string $ownerPhone = null): array
    {
        $ownerPhone ??= '+98912'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

        $tenant = app(CreateTenant::class)->handle(new CreateTenantData(
            name: 'کافه '.$slug,
            slug: $slug,
            ownerName: 'مالک '.$slug,
            ownerEmail: null,
            ownerPhoneE164: $ownerPhone,
            ownerPassword: 'password',
            subdomainBase: 'menu.test',
        ));
        $tenant->update(['status' => TenantStatus::Active]);
        // Tests run with everything enabled unless they set up a plan themselves (see BillingTest).
        $this->inTenant($tenant, fn () => Subscription::query()->firstOrFail()->update([
            'plan_id' => Plan::query()->where('key', 'chain')->value('id'),
            'status' => 'active',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addYears(10),
        ]));

        return ['tenant' => $tenant, 'owner' => User::query()->where('phone_e164', $ownerPhone)->firstOrFail()];
    }

    /** Adds a staff member with the given role key to the tenant and returns the user. */
    protected function addMember(Tenant $tenant, User $actor, string $roleKey): User
    {
        $phone = '+98935'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

        return $this->inTenant($tenant, function () use ($actor, $roleKey, $phone) {
            $role = Role::query()->where('key', $roleKey)->firstOrFail();

            return app(AddTeamMember::class)->handle($actor, 'عضو '.$roleKey, $phone, 'password', [$role->getKey()])->user;
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return app(TenantContext::class)->runAs($tenant, $callback(...));
    }

    /** Real Sanctum token (exercises the full auth path, including tenant binding). */
    protected function staffToken(User $user): string
    {
        return $user->createToken('test', ['staff'])->plainTextToken;
    }

    /** @return array<string, string> */
    protected function staffHeaders(User $user, ?Tenant $tenant = null): array
    {
        return array_filter([
            'Authorization' => 'Bearer '.$this->staffToken($user),
            'X-Tenant' => $tenant?->slug,
            'Accept' => 'application/json',
        ]);
    }

    protected function refreshApplicationState(): void
    {
        // Each HTTP call in a test should start with a clean auth + tenant state, like a real request.
        $this->app['auth']->forgetGuards();
        $this->app->forgetScopedInstances();
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->refreshApplicationState();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
