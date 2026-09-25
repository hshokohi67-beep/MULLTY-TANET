<?php

namespace Tests\Feature\Support;

use App\Modules\Identity\Models\User;
use App\Support\Ops\Console\PreflightCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Go-live preflight, and the role names the help centre shows. */
final class PreflightAndRolesTest extends TestCase
{
    public function test_preflight_fails_outside_a_production_setup_and_says_why(): void
    {
        User::factory()->platformAdmin()->create(['email' => 'admin@example.test']);

        $this->assertSame(1, Artisan::call('ops:preflight', ['--json' => true]));
        $report = json_decode(Artisan::output(), true);
        $failed = collect($report['checks'])->where('ok', false)->where('blocking', true)->pluck('key')->all();

        foreach (['env', 'https', 'database', 'queue', 'payments', 'sms', 'demo-users', 'admin'] as $key) {
            $this->assertContains($key, $failed, "preflight should flag {$key}");
        }
    }

    public function test_preflight_passes_with_a_production_like_configuration(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'app.debug' => false, 'app.url' => 'https://api.cafeyar.ir', 'payments.storefront_url' => 'https://cafeyar.ir',
            'database.connections.sqlite.driver' => 'mysql', 'queue.default' => 'database', 'cache.default' => 'redis',
            'payments.driver' => 'zarinpal', 'payments.gateways.zarinpal.sandbox' => false, 'billing.gateway' => 'zarinpal', 'sms.default' => 'kavenegar',
            'session.secure' => true, 'app.trusted_proxies' => '10.0.0.0/8', 'logging.default' => 'json', 'backup.disk' => 's3', 'filesystems.media_disk' => 's3',
        ]);
        User::factory()->platformAdmin()->create(['email' => 'ops@cafeyar.ir']);

        try {
            $failed = collect(app(PreflightCommand::class)->checks())->where('ok', false)->where('blocking', true)->pluck('key')->all();
            $this->assertSame([], $failed);
        } finally {
            config(['database.connections.sqlite.driver' => 'sqlite']);
        }
    }

    public function test_me_includes_role_names_for_display(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner();
        $cashier = $this->addMember($tenant, $owner, 'cashier');

        $membership = $this->getJson('/api/v1/auth/staff/me', $this->staffHeaders($cashier))->assertOk()->json('memberships.0');
        $this->assertSame([['key' => 'cashier', 'name' => 'صندوق‌دار']], $membership['roles']);
        $this->assertContains('orders.view', $membership['permissions']);
    }
}
