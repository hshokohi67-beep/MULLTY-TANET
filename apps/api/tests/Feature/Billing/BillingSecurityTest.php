<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Actions\StartTrial;
use App\Modules\Billing\Models\Addon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\BillingPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\BillingGateway;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\Feature\Payments\PaymentsTestCase;

/**
 * Attacks on billing: price tampering, forged/replayed payments, cross-tenant access, the fake
 * gateway outside dev, free credit, read-only bypass and state leaks, platform input abuse, floods.
 */
final class BillingSecurityTest extends PaymentsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00', 'Asia/Tehran'));
        $this->inTenant($this->tenant, function (): void {
            Subscription::query()->delete();
            app(StartTrial::class)->handle();
        });
    }

    /** @return array<string, string> */
    private function h(?User $user = null): array
    {
        return $this->staffHeaders($user ?? $this->owner, $this->tenant);
    }

    private function planId(string $key): string
    {
        return (string) Plan::query()->where('key', $key)->value('id');
    }

    private function addonId(string $key): string
    {
        return (string) Addon::query()->where('key', $key)->value('id');
    }

    /** @param  array<string, mixed>  $extra */
    private function buy(string $plan, array $extra = [], ?array $headers = null): TestResponse
    {
        return $this->postJson('/api/v1/billing/checkout', ['plan_id' => $this->planId($plan), 'cycle' => 'monthly', ...$extra], $headers ?? $this->h());
    }

    private function authority(string $redirect): string
    {
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $q);

        return (string) $q['Authority'];
    }

    public function test_prices_and_catalogue_cannot_be_tampered_with(): void
    {
        // Client-sent amounts are ignored: the server prices everything.
        $invoice = $this->buy('pro', ['total' => 1, 'amount' => 1, 'subtotal' => 1, 'credit' => 99_999_999, 'vat' => 0])->assertCreated()->json('data.invoice');
        $this->assertSame([11_900_000, 0, 1_190_000, 13_090_000], [$invoice['subtotal'], $invoice['credit'], $invoice['vat'], $invoice['total']]);

        // Hidden plans, add-ons for other plans, private add-ons, silly quantities, duplicates.
        Plan::query()->where('key', 'starter')->update(['is_public' => false]);
        $this->buy('starter')->assertUnprocessable()->assertJsonValidationErrors('plan_id');
        $this->buy('pro', ['cycle' => 'weekly'])->assertUnprocessable()->assertJsonValidationErrors('cycle');
        $this->buy('chain', ['addons' => [['addon_id' => $this->addonId('custom_domain'), 'quantity' => 1]]])->assertStatus(422)->assertJsonPath('code', 'addon_unavailable');
        $this->buy('pro', ['addons' => [['addon_id' => $this->addonId('extra_staff'), 'quantity' => 21]]])->assertUnprocessable()->assertJsonValidationErrors('addons.0.quantity');
        $this->buy('pro', ['addons' => [['addon_id' => $this->addonId('extra_staff'), 'quantity' => -3]]])->assertUnprocessable();
        $dup = ['addon_id' => $this->addonId('extra_branch'), 'quantity' => 1];
        $this->buy('pro', ['addons' => [$dup, $dup]])->assertUnprocessable()->assertJsonValidationErrors('addons.0.addon_id');
        Addon::query()->where('key', 'extra_branch')->update(['is_public' => false]);
        $this->buy('pro', ['addons' => [$dup]])->assertUnprocessable()->assertJsonValidationErrors('addons.0.addon_id');
    }

    public function test_payments_cannot_be_forged_replayed_or_taken_across_tenants(): void
    {
        $mine = $this->buy('pro')->assertCreated()->json('data');
        $authority = $this->authority($mine['redirect_url']);

        // Another café: can't see, pay or verify this invoice, nor use its authority on its own invoice.
        ['tenant' => $other, 'owner' => $otherOwner] = $this->createTenantWithOwner('cafe-other');
        $oh = $this->staffHeaders($otherOwner, $other);
        $this->getJson("/api/v1/billing/invoices/{$mine['invoice']['id']}", $oh)->assertNotFound();
        $this->postJson("/api/v1/billing/invoices/{$mine['invoice']['id']}/verify", ['authority' => $authority], $oh)->assertNotFound();
        $this->postJson("/api/v1/billing/invoices/{$mine['invoice']['id']}/pay", [], $oh)->assertNotFound();
        $theirs = $this->postJson('/api/v1/billing/checkout', ['plan_id' => $this->planId('chain'), 'cycle' => 'monthly'], $oh)->json('data.invoice.id');
        $this->postJson("/api/v1/billing/invoices/{$theirs}/verify", ['authority' => $authority], $oh)->assertStatus(422)->assertJsonPath('code', 'invoice_not_payable');
        $this->assertSame('open', $this->inTenant($other, fn () => BillingInvoice::query()->value('status')));

        // A made-up authority pays nothing; the real one pays once, and replays change nothing.
        $this->postJson("/api/v1/billing/invoices/{$mine['invoice']['id']}/verify", ['authority' => 'FAKE'.str_repeat('X', 28)], $this->h())->assertStatus(422);
        $this->postJson("/api/v1/billing/invoices/{$mine['invoice']['id']}/verify", ['authority' => $authority], $this->h())->assertJsonPath('data.paid', true);
        $end = $this->inTenant($this->tenant, fn () => Subscription::query()->value('current_period_end'));
        foreach (range(1, 3) as $_) {
            $this->postJson("/api/v1/billing/invoices/{$mine['invoice']['id']}/verify", ['authority' => $authority], $this->h())->assertJsonPath('data.paid', true);
        }
        $this->assertEquals($end, $this->inTenant($this->tenant, fn () => Subscription::query()->value('current_period_end')));
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => BillingPayment::query()->where('status', 'paid')->count()));
        $this->postJson("/api/v1/billing/invoices/{$mine['invoice']['id']}/pay", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'invoice_not_payable');
    }

    public function test_paying_one_invoice_voids_the_others_so_nobody_pays_twice(): void
    {
        $first = $this->buy('pro')->json('data');
        $this->postJson("/api/v1/billing/invoices/{$first['invoice']['id']}/verify", ['authority' => $this->authority($first['redirect_url'])], $this->h())->assertJsonPath('data.paid', true);

        // A week before the end the renewal is issued; the owner then upgrades instead.
        $this->travel(24)->days();
        $this->artisan('billing:renewals')->assertSuccessful();
        $renewal = $this->inTenant($this->tenant, fn () => BillingInvoice::query()->where('kind', 'renewal')->firstOrFail());
        $up = $this->buy('chain')->json('data');
        $this->postJson("/api/v1/billing/invoices/{$up['invoice']['id']}/verify", ['authority' => $this->authority($up['redirect_url'])], $this->h())->assertJsonPath('data.paid', true);

        $this->assertSame('void', $this->inTenant($this->tenant, fn () => $renewal->refresh()->status));
        $this->postJson("/api/v1/billing/invoices/{$renewal->id}/pay", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'invoice_not_payable');
    }

    public function test_gifted_days_earn_no_credit(): void
    {
        // Active without any paid invoice (a platform extension): switching up gets no credit.
        $admin = User::factory()->platformAdmin()->create();
        $this->inTenant($this->tenant, fn () => Subscription::query()->firstOrFail()->update(['status' => 'active', 'current_period_start' => now(), 'current_period_end' => now()->addDays(30)]));
        $this->postJson("/api/v1/platform/tenants/{$this->tenant->id}/extend", ['days' => 60, 'reason' => 'هدیه'], $this->staffHeaders($admin))->assertOk();

        $quote = $this->postJson('/api/v1/billing/quote', ['plan_id' => $this->planId('chain'), 'cycle' => 'monthly'], $this->h())->json('data');
        $this->assertSame(['pay_now', 0], [$quote['mode'], $quote['credit']]);
    }

    public function test_the_fake_gateway_is_refused_outside_dev_and_test(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->assertThrows(fn () => app(BillingGateway::class)->make('fake'), LogicException::class);
            $this->assertThrows(fn () => app(BillingGateway::class)->driver(), LogicException::class);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_read_only_cannot_be_bypassed_and_does_not_leak_to_strangers(): void
    {
        $this->travel(30)->days(); // trial + grace are over

        // Anonymous writes get the normal 401, not a hint about the subscription.
        $this->patchJson('/api/v1/tenant', ['name' => 'x'], ['X-Tenant' => $this->tenant->slug, 'Accept' => 'application/json'])->assertUnauthorized();
        // Signed-in staff and the public storefront are refused; reads keep working.
        $this->patchJson('/api/v1/tenant', ['name' => 'x'], $this->h())->assertStatus(402)->assertJsonPath('code', 'subscription_read_only');
        $this->postJson('/api/v1/branches', ['name' => 'x', 'slug' => 'x'], $this->h())->assertStatus(402);
        $this->postJson('/api/v1/public/carts', ['order_type' => 'takeaway', 'branch_id' => $this->branch->id], $this->publicHeaders())->assertStatus(402)->assertJsonPath('code', 'store_unavailable');
        $this->getJson('/api/v1/catalog/products', $this->h())->assertOk();

        // Only the billing routes stay writable, and only for someone allowed to pay.
        $cashier = $this->addMemberDirectly('cashier');
        $this->postJson('/api/v1/billing/checkout', ['plan_id' => $this->planId('pro'), 'cycle' => 'monthly'], $this->h($cashier))->assertForbidden();
        $this->getJson('/api/v1/billing/status', $this->h($cashier))->assertOk()->assertJsonPath('data.state', 'read_only');
        $this->buy('pro')->assertCreated();
    }

    public function test_platform_inputs_are_validated_and_owners_cannot_reach_them(): void
    {
        $this->getJson('/api/v1/platform/plans', $this->h())->assertForbidden();
        $this->postJson("/api/v1/platform/tenants/{$this->tenant->id}/extend", ['days' => 30, 'reason' => 'x'], $this->h())->assertForbidden();

        $ah = $this->staffHeaders(User::factory()->platformAdmin()->create());
        $url = "/api/v1/platform/tenants/{$this->tenant->id}";
        $this->postJson("{$url}/overrides", ['feature' => 'reports', 'value' => 5, 'reason' => 'x'], $ah)->assertStatus(422)->assertJsonPath('code', 'unknown_feature');
        $this->postJson("{$url}/overrides", ['feature' => 'branches', 'value' => -1, 'reason' => 'x'], $ah)->assertStatus(422);
        $this->postJson("{$url}/overrides", ['feature' => 'root', 'value' => true, 'reason' => 'x'], $ah)->assertUnprocessable()->assertJsonValidationErrors('feature');
        $this->postJson("{$url}/overrides", ['feature' => 'reports', 'value' => true, 'reason' => 'x', 'expires_at' => now()->subDay()->toIso8601String()], $ah)->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->postJson("{$url}/extend", ['days' => 0, 'reason' => 'x'], $ah)->assertUnprocessable();
        $this->postJson("{$url}/extend", ['days' => 5000, 'reason' => 'x'], $ah)->assertUnprocessable();

        $plan = Plan::query()->where('key', 'pro')->firstOrFail();
        $this->putJson("/api/v1/platform/plans/{$plan->id}", [
            'key' => 'hacked', 'is_trial_plan' => false, 'name' => 'حرفه‌ای', 'tagline' => null, 'monthly_price' => 1, 'yearly_price' => 1, 'is_public' => true,
            'features' => [...$plan->features, 'branches' => 'many'],
        ], $ah)->assertUnprocessable()->assertJsonValidationErrors('features.branches');
        $this->assertSame(['pro', true, 11_900_000], [$plan->refresh()->key, $plan->is_trial_plan, $plan->monthly_price]);

        // Confirming a transfer only works on an open invoice of that café.
        $invoice = $this->buy('pro')->json('data.invoice.id');
        ['tenant' => $other] = $this->createTenantWithOwner('cafe-else');
        $this->postJson("/api/v1/platform/tenants/{$other->id}/invoices/{$invoice}/mark-paid", ['reference' => 'x'], $ah)->assertNotFound();
        $this->postJson("{$url}/invoices/{$invoice}/mark-paid", ['reference' => 'x'], $ah)->assertOk();
        $this->postJson("{$url}/invoices/{$invoice}/mark-paid", ['reference' => 'x'], $ah)->assertStatus(422)->assertJsonPath('code', 'invoice_not_payable');
    }

    public function test_checkout_and_verify_are_rate_limited(): void
    {
        foreach (range(1, 10) as $_) {
            $this->buy('pro')->assertCreated();
        }
        $this->buy('pro')->assertStatus(429);
        // Only one open checkout invoice survives the flood.
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => BillingInvoice::query()->where('status', 'open')->count()));
    }

    private function addMemberDirectly(string $role): User
    {
        // Created while writable: members are added before the subscription runs out.
        $this->travel(-30)->days();
        $user = $this->addMember($this->tenant, $this->owner, $role);
        $this->travel(30)->days();

        return $user;
    }
}
