<?php

namespace Tests\Feature\Core;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** «شروع رایگان»: a café signs itself up with a verified mobile and lands in its panel on a trial. */
final class SignupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('signup:127.0.0.1');
    }

    private string $phone = '+989123456789';

    private function lastCode(): string
    {
        return (string) $this->sms->lastCodeFor($this->phone);
    }

    /** @return array<string, string> */
    private function form(array $override = []): array
    {
        return [
            'cafe_name' => 'کافه نارنج', 'slug' => 'naranj', 'owner_name' => 'سارا رضایی', 'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'password' => 'narenj1405', 'code' => $this->lastCode(), ...$override,
        ];
    }

    public function test_address_suggestions_follow_the_persian_name_and_skip_taken_or_reserved_ones(): void
    {
        $this->getJson('/api/v1/public/signup/slug?name='.urlencode('کافه سیب'))->assertOk()->assertJsonPath('data.suggestion', 'cafe-sib');
        $this->createTenantWithOwner('cafe-sib');
        $this->getJson('/api/v1/public/signup/slug?name='.urlencode('کافه سیب').'&slug=cafe-sib')->assertOk()
            ->assertJsonPath('data.suggestion', 'cafe-sib-2')->assertJsonPath('data.available', false);
        $this->getJson('/api/v1/public/signup/slug?slug=api')->assertOk()->assertJsonPath('data.valid', false);
        $this->getJson('/api/v1/public/signup/slug?slug=Bad_Slug')->assertOk()->assertJsonPath('data.valid', false);
        $this->getJson('/api/v1/public/signup/slug?slug=eram')->assertOk()->assertJsonPath('data.available', true);
    }

    public function test_a_verified_owner_gets_a_trial_cafe_and_a_working_session(): void
    {
        $this->postJson('/api/v1/public/signup/otp', ['phone' => '09123456789'])->assertOk()->assertJsonPath('data.resend_after', config('otp.resend_cooldown_seconds'));
        $this->assertNotSame('', $this->lastCode());

        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';
        $this->postJson('/api/v1/public/signup', $this->form(['code' => $wrong]))->assertUnprocessable();
        $response = $this->postJson('/api/v1/public/signup', $this->form())->assertCreated()->assertJsonPath('data.tenant.slug', 'naranj');

        $tenant = Tenant::query()->where('slug', 'naranj')->sole();
        $this->inTenant($tenant, function (): void {
            $this->assertSame('trialing', Subscription::query()->sole()->status);
            $this->assertSame(1, Branch::query()->count());
        });
        $this->assertSame('سارا رضایی', User::query()->where('phone_e164', '+989123456789')->sole()->name);

        // The token opens the new café's panel.
        $this->getJson('/api/v1/tenant', ['Authorization' => 'Bearer '.$response->json('data.token'), 'X-Tenant' => 'naranj'])->assertOk()->assertJsonPath('data.name', 'کافه نارنج');

        // The code was consumed; the same phone can't sign up twice.
        $this->postJson('/api/v1/public/signup/otp', ['phone' => '09123456789'])->assertUnprocessable()->assertJsonPath('code', 'signup_phone_taken');
    }

    public function test_guards_honeypot_taken_address_weak_password_and_closed_signup(): void
    {
        $this->phone = '+989121112233';
        $this->postJson('/api/v1/public/signup/otp', ['phone' => '09121112233', 'website' => 'http://spam'])->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->postJson('/api/v1/public/signup/otp', ['phone' => '09121112233'])->assertOk();

        $this->createTenantWithOwner('taken');
        $this->postJson('/api/v1/public/signup', $this->form(['phone' => '09121112233', 'slug' => 'taken']))->assertUnprocessable()->assertJsonPath('code', 'signup_slug_taken');
        $this->postJson('/api/v1/public/signup', $this->form(['phone' => '09121112233', 'password' => 'short']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/public/signup', $this->form(['phone' => '09121112233', 'slug' => 'admin']))->assertUnprocessable()->assertJsonPath('code', 'signup_slug_taken');

        config(['tenancy.self_signup' => false]);
        $this->postJson('/api/v1/public/signup/otp', ['phone' => '09124445566'])->assertForbidden()->assertJsonPath('code', 'signup_closed');
    }

    public function test_the_public_plans_show_prices_and_features_but_nothing_internal(): void
    {
        $data = $this->getJson('/api/v1/public/plans')->assertOk()->json('data');
        $this->assertNotEmpty($data['plans']);
        $this->assertArrayNotHasKey('id', $data['plans'][0]);
        $this->assertArrayHasKey('monthly_price', $data['plans'][0]);
        $this->assertSame(14, $data['trial_days']);
        $this->assertNotEmpty(array_filter($data['features'], fn (array $f) => $f['key'] === 'inventory'));
    }
}
