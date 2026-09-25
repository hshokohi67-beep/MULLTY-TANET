<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Actions\StartTrial;
use App\Modules\Billing\Models\Addon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionAddon;
use App\Modules\Identity\Models\User;
use App\Support\Entitlements\PlanLimitReachedException;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Payments\PaymentsTestCase;

/**
 * Prices (rial): starter 4,900,000 • pro 11,900,000 • chain 29,000,000 per month; yearly = 10×; VAT 10%.
 * The test base puts every tenant on an active Chain plan; these tests set their own subscription.
 */
final class BillingTest extends PaymentsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00', 'Asia/Tehran'));
    }

    /** @return array<string, string> */
    private function h(?User $user = null): array
    {
        return $this->staffHeaders($user ?? $this->owner, $this->tenant);
    }

    private function plan(string $key): Plan
    {
        return Plan::query()->where('key', $key)->firstOrFail();
    }

    /** Back to a brand-new café: the 14-day Pro trial. */
    private function freshTrial(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Subscription::query()->delete();
            app(StartTrial::class)->handle();
        });
    }

    /** @param  array<string, int>  $addons  addon key => quantity */
    private function onPlan(string $key, int $daysLeft = 20, array $addons = []): void
    {
        $this->inTenant($this->tenant, function () use ($key, $daysLeft, $addons): void {
            $s = Subscription::query()->firstOrFail();
            $s->update(['plan_id' => $this->plan($key)->id, 'cycle' => 'monthly', 'status' => 'active', 'current_period_start' => now()->subDays(30 - $daysLeft), 'current_period_end' => now()->addDays($daysLeft)]);
            SubscriptionAddon::query()->delete();
            foreach ($addons as $addon => $quantity) {
                SubscriptionAddon::query()->create(['subscription_id' => $s->id, 'addon_id' => Addon::query()->where('key', $addon)->value('id'), 'quantity' => $quantity]);
            }
        });
    }

    private function buy(string $plan, string $cycle = 'monthly'): TestResponse
    {
        return $this->postJson('/api/v1/billing/checkout', ['plan_id' => $this->plan($plan)->id, 'cycle' => $cycle], $this->h());
    }

    private function payInvoice(string $redirectUrl, string $invoiceId): TestResponse
    {
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $q);
        $this->assertSame($invoiceId, $q['invoice']);

        return $this->postJson("/api/v1/billing/invoices/{$invoiceId}/verify", ['authority' => $q['Authority']], $this->h());
    }

    public function test_trial_then_grace_then_read_only_keeps_reads_and_paying(): void
    {
        $this->freshTrial();
        $this->getJson('/api/v1/billing/status', $this->h())->assertOk()
            ->assertJsonPath('data.state', 'trial')->assertJsonPath('data.plan.key', 'pro')->assertJsonPath('data.days_left', 14)
            ->assertJsonPath('data.features.inventory', true)->assertJsonPath('data.features.branches', 2);

        // Five days before the end the owner's bell says so.
        $this->travel(10)->days();
        $alert = collect($this->getJson('/api/v1/dashboard/alerts', $this->h())->json('data'))->firstWhere('type', 'subscription');
        $this->assertSame(['warning', '/dashboard/billing'], [$alert['severity'], $alert['href']]);
        $this->travel(-10)->days();

        // Day 16: the trial is over but there is a week of grace with full access.
        $this->travel(16)->days();
        $this->getJson('/api/v1/billing/status', $this->h())->assertJsonPath('data.state', 'grace')->assertJsonPath('data.days_left', 5);
        $this->patchJson('/api/v1/tenant', ['name' => 'کافه تازه'], $this->h())->assertOk();

        // Day 22: read-only. Reads and billing work; writes (staff and public) are refused.
        $this->travel(6)->days();
        $this->getJson('/api/v1/billing/status', $this->h())->assertJsonPath('data.state', 'read_only');
        $this->patchJson('/api/v1/tenant', ['name' => 'کافه دیگر'], $this->h())->assertStatus(402)->assertJsonPath('code', 'subscription_read_only');
        $this->getJson('/api/v1/tenant', $this->h())->assertOk()->assertJsonPath('data.name', 'کافه تازه');
        $this->postJson('/api/v1/public/tables/session', ['qr_token' => $this->qrToken], $this->publicHeaders())->assertStatus(402)->assertJsonPath('code', 'store_unavailable');
        $this->postJson('/api/v1/billing/quote', ['plan_id' => $this->plan('pro')->id, 'cycle' => 'monthly'], $this->h())->assertOk();

        // Paying brings everything back at once.
        $res = $this->buy('pro')->assertCreated()->json('data');
        $this->payInvoice($res['redirect_url'], $res['invoice']['id'])->assertOk()->assertJsonPath('data.paid', true);
        $this->patchJson('/api/v1/tenant', ['name' => 'کافه دیگر'], $this->h())->assertOk();
        $this->getJson('/api/v1/billing/status', $this->h())->assertJsonPath('data.state', 'active');
    }

    public function test_plan_features_limits_addons_and_overrides_gate_every_module(): void
    {
        $this->onPlan('starter');

        foreach (['/api/v1/reports/summary', '/api/v1/inventory/ingredients', '/api/v1/expenses', '/api/v1/loyalty/program', '/api/v1/stories'] as $url) {
            $this->getJson($url, $this->h())->assertStatus(402)->assertJsonPath('code', 'feature_not_in_plan');
        }
        $this->getJson('/api/v1/public/stories', $this->publicHeaders())->assertOk()->assertJsonPath('data', []);
        // Plan-bound widgets disappear from the catalogue and can't be fetched.
        $this->assertNotContains('food_cost', array_column($this->getJson('/api/v1/dashboard/layout', $this->h())->json('data.catalog'), 'key'));
        $this->getJson('/api/v1/dashboard/widgets/food_cost', $this->h())->assertNotFound();
        $this->getJson('/api/v1/billing/status', $this->h())->assertJsonPath('data.features.online_payments', false);

        // One branch on Starter; the add-on raises the limit.
        $branch = ['name' => 'شعبه دو', 'slug' => 'two'];
        $this->postJson('/api/v1/branches', $branch, $this->h())->assertStatus(402)->assertJsonPath('code', 'plan_limit_reached');
        $this->onPlan('starter', addons: ['extra_branch' => 1]);
        $this->postJson('/api/v1/branches', $branch, $this->h())->assertCreated();

        // Five team members on Starter (the owner is one).
        foreach (range(1, 4) as $_) {
            $this->addMember($this->tenant, $this->owner, 'cashier');
        }
        $this->expectExceptionObject(new PlanLimitReachedException('staff', 'اعضای تیم', 5));
        try {
            $this->addMember($this->tenant, $this->owner, 'cashier');
        } finally {
            // A platform grant switches reports on until it expires.
            $admin = User::factory()->platformAdmin()->create();
            $this->postJson("/api/v1/platform/tenants/{$this->tenant->id}/overrides", ['feature' => 'reports', 'value' => true, 'reason' => 'هدیه', 'expires_at' => now()->addDays(3)->toIso8601String()], $this->staffHeaders($admin))->assertCreated();
            $this->getJson('/api/v1/reports/summary', $this->h())->assertOk();
            $this->travel(4)->days();
            $this->getJson('/api/v1/reports/summary', $this->h())->assertStatus(402);
        }
    }

    public function test_quote_checkout_verify_is_idempotent_and_proration_is_fair(): void
    {
        $this->freshTrial();

        $q = $this->postJson('/api/v1/billing/quote', ['plan_id' => $this->plan('pro')->id, 'cycle' => 'monthly'], $this->h())->assertOk()->json('data');
        $this->assertSame(['pay_now', 11_900_000, 0, 1_190_000, 13_090_000], [$q['mode'], $q['subtotal'], $q['credit'], $q['vat'], $q['total']]);
        $this->assertSame('2026-10-26', CarbonImmutable::parse($q['period_end'])->setTimezone('Asia/Tehran')->toDateString());

        $res = $this->buy('pro')->assertCreated()->json('data');
        $this->assertStringContainsString('/billing/return?invoice=', $res['redirect_url']);
        $this->payInvoice($res['redirect_url'], $res['invoice']['id'])->assertJsonPath('data.paid', true)->assertJsonPath('data.invoice.status', 'paid');
        $end = $this->inTenant($this->tenant, fn () => Subscription::query()->value('current_period_end'));
        // A refresh of the return page verifies again: nothing changes.
        $this->payInvoice($res['redirect_url'], $res['invoice']['id'])->assertJsonPath('data.paid', true);
        $this->assertEquals($end, $this->inTenant($this->tenant, fn () => Subscription::query()->value('current_period_end')));
        $this->assertStringStartsWith('1405-', $res['invoice']['number']);

        // Same selection → renewal from the current end; a bigger plan half-way → credit for the unused half.
        $this->assertSame('renew', $this->postJson('/api/v1/billing/quote', ['plan_id' => $this->plan('pro')->id, 'cycle' => 'monthly'], $this->h())->json('data.mode'));
        $this->travel(15)->days();
        $up = $this->postJson('/api/v1/billing/quote', ['plan_id' => $this->plan('chain')->id, 'cycle' => 'monthly'], $this->h())->json('data');
        $this->assertSame('pay_now', $up['mode']);
        $this->assertEqualsWithDelta(11_900_000 / 2, $up['credit'], 11_900_000 * 0.03);
        $this->assertSame(0, $up['credit'] % 10);
        $this->assertSame(29_000_000 - $up['credit'] + $up['vat'], $up['total']);

        // Going down is scheduled for the period end (nothing to pay), with a plain-words warning.
        $down = $this->postJson('/api/v1/billing/quote', ['plan_id' => $this->plan('starter')->id, 'cycle' => 'monthly'], $this->h())->json('data');
        $this->assertSame(['scheduled', 0], [$down['mode'], $down['total']]);
        $this->assertStringContainsString('خاموش می‌شوند: «پرداخت آنلاین»، «باشگاه مشتریان»، «انبار و خرید»', implode(' ', $down['warnings']));
        $this->buy('starter')->assertOk()->assertJsonPath('data.mode', 'scheduled');
        $this->getJson('/api/v1/billing', $this->h())->assertJsonPath('data.subscription.scheduled.plan.key', 'starter');

        // A wrong authority can't pay anything.
        $this->postJson("/api/v1/billing/invoices/{$res['invoice']['id']}/verify", ['authority' => 'nope'], $this->h())->assertStatus(422)->assertJsonPath('code', 'invoice_not_payable');
    }

    public function test_renewal_invoice_reminders_and_cancellation(): void
    {
        $this->onPlan('pro', daysLeft: 5);
        $this->inTenant($this->tenant, fn () => Subscription::query()->firstOrFail()->update(['scheduled_plan_id' => $this->plan('starter')->id, 'scheduled_cycle' => 'monthly']));

        $this->artisan('billing:renewals')->assertSuccessful();
        $this->artisan('billing:renewals')->assertSuccessful(); // idempotent
        $invoices = $this->getJson('/api/v1/billing/invoices', $this->h())->json('data');
        $this->assertCount(1, $invoices);
        $this->assertSame(['renewal', 'open', 'پایه', 5_390_000], [$invoices[0]['kind'], $invoices[0]['status'], $invoices[0]['plan']['name'], $invoices[0]['total']]);
        $this->assertCount(1, $this->sms->messages);
        $this->assertStringContainsString('۵ روز دیگر', $this->sms->messages[0]->text);

        // Paying the renewal continues from the current end, on the scheduled plan.
        $end = $this->inTenant($this->tenant, fn () => Subscription::query()->value('current_period_end'));
        $url = $this->postJson("/api/v1/billing/invoices/{$invoices[0]['id']}/pay", [], $this->h())->assertOk()->json('data.redirect_url');
        $this->payInvoice($url, $invoices[0]['id'])->assertJsonPath('data.paid', true);
        $s = $this->inTenant($this->tenant, fn () => Subscription::query()->with('plan')->firstOrFail());
        $this->assertSame('starter', $s->plan->key);
        $this->assertEquals(CarbonImmutable::instance($end)->addMonthNoOverflow(), $s->current_period_end);
        $this->assertTrue($s->current_period_start->lt(now()));
        $this->assertNull($s->scheduled_plan_id);

        $this->postJson('/api/v1/billing/cancel', [], $this->h())->assertOk();
        $this->getJson('/api/v1/billing/status', $this->h())->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.state', 'active');
        $this->postJson('/api/v1/billing/resume', [], $this->h())->assertOk();
        $this->postJson('/api/v1/billing/cancel', [], $this->h())->assertOk();
        // A cancelled subscription ends without grace.
        $this->travel(40)->days();
        $this->getJson('/api/v1/billing/status', $this->h())->assertJsonPath('data.state', 'read_only');
        $this->postJson('/api/v1/billing/resume', [], $this->h())->assertStatus(422)->assertJsonPath('code', 'subscription_not_resumable');
    }

    public function test_permissions_and_platform_admin_tools(): void
    {
        $manager = $this->addMember($this->tenant, $this->owner, 'manager');
        $this->getJson('/api/v1/billing', $this->h($manager))->assertForbidden();
        $this->getJson('/api/v1/billing/status', $this->h($manager))->assertOk();
        $this->getJson('/api/v1/platform/subscriptions', $this->h())->assertForbidden();

        $this->freshTrial();
        $admin = User::factory()->platformAdmin()->create();
        $ah = $this->staffHeaders($admin);
        $row = collect($this->getJson('/api/v1/platform/subscriptions', $ah)->assertOk()->json('data'))->firstWhere('tenant.id', $this->tenant->id);
        $this->assertSame(['trial', 'pro'], [$row['subscription']['state'], $row['subscription']['plan']['key']]);

        $this->postJson("/api/v1/platform/tenants/{$this->tenant->id}/extend", ['days' => 10, 'reason' => 'راه‌اندازی دیر'], $ah)->assertOk()->assertJsonPath('data.state', 'trial');
        $this->getJson('/api/v1/billing/status', $this->h())->assertJsonPath('data.days_left', 24);

        // A bank transfer: the owner starts a checkout, the platform confirms it.
        $invoice = $this->buy('chain', 'yearly')->assertCreated()->json('data.invoice');
        $this->assertSame([290_000_000, 319_000_000], [$invoice['subtotal'], $invoice['total']]);
        $this->postJson("/api/v1/platform/tenants/{$this->tenant->id}/invoices/{$invoice['id']}/mark-paid", ['reference' => 'حواله ۱۲۳'], $ah)->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.paid_via', 'transfer');
        $this->getJson('/api/v1/billing/status', $this->h())->assertJsonPath('data.plan.key', 'chain')->assertJsonPath('data.state', 'active');
        $this->assertSame('1405-', substr($this->inTenant($this->tenant, fn () => BillingInvoice::query()->value('number')), 0, 5));

        // Plan editing.
        $plan = $this->plan('starter');
        $this->putJson("/api/v1/platform/plans/{$plan->id}", [
            'name' => 'پایه', 'tagline' => null, 'monthly_price' => 5_900_000, 'yearly_price' => 59_000_000, 'is_public' => true,
            'features' => [...$plan->features, 'products' => 200],
        ], $ah)->assertOk()->assertJsonPath('data.monthly_price', 5_900_000)->assertJsonPath('data.features.products', 200);
    }
}
