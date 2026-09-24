<?php

namespace Tests\Feature\Identity;

use App\Modules\Customers\Models\Customer;
use App\Support\Localization\PersianNumber;
use App\Support\Tenancy\TenantContext;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CustomerOtpTest extends TestCase
{
    private const PHONE = '+989121234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTenantWithOwner('cafe-a');
        $this->createTenantWithOwner('cafe-b');
    }

    private function requestCode(string $tenant = 'cafe-a', string $phone = '09121234567'): TestResponse
    {
        return $this->postJson('/api/v1/auth/customer/otp/request', ['phone' => $phone], ['X-Tenant' => $tenant]);
    }

    private function verify(string $code, string $tenant = 'cafe-a', string $phone = '09121234567'): TestResponse
    {
        return $this->postJson('/api/v1/auth/customer/otp/verify', ['phone' => $phone, 'code' => $code], ['X-Tenant' => $tenant]);
    }

    public function test_full_login_flow_creates_a_tenant_scoped_customer(): void
    {
        $this->requestCode(phone: '۰۹۱۲۱۲۳۴۵۶۷')
            ->assertOk()
            ->assertJsonPath('message', 'کد تایید برای شما پیامک شد.')
            ->assertJsonMissingPath('code')
            ->assertJsonMissingPath('otp');

        $code = $this->sms->lastCodeFor(self::PHONE);
        $this->assertMatchesRegularExpression('/^\d{5}$/', (string) $code);

        $token = $this->verify(PersianNumber::toPersian((string) $code))
            ->assertOk()
            ->assertJsonPath('is_new', true)
            ->assertJsonPath('customer.phone', '09121234567')
            ->json('token');

        $this->getJson('/api/v1/auth/customer/me', ['Authorization' => 'Bearer '.$token, 'X-Tenant' => 'cafe-a'])
            ->assertOk()
            ->assertJsonPath('data.phone', '09121234567');
    }

    public function test_code_is_single_use(): void
    {
        $this->requestCode();
        $code = (string) $this->sms->lastCodeFor(self::PHONE);

        $this->verify($code)->assertOk();
        $this->verify($code)->assertUnprocessable()->assertJsonPath('code', 'otp_expired');
    }

    public function test_customer_token_is_rejected_by_another_tenant(): void
    {
        $this->requestCode();
        $token = $this->verify((string) $this->sms->lastCodeFor(self::PHONE))->json('token');

        $this->getJson('/api/v1/auth/customer/me', ['Authorization' => 'Bearer '.$token, 'X-Tenant' => 'cafe-b'])
            ->assertUnauthorized();
    }

    public function test_same_phone_at_two_tenants_is_two_customers(): void
    {
        $this->requestCode('cafe-a');
        $a = $this->verify((string) $this->sms->lastCodeFor(self::PHONE), 'cafe-a')->json('customer.id');

        $this->travel(2)->minutes();
        $this->requestCode('cafe-b');
        $b = $this->verify((string) $this->sms->lastCodeFor(self::PHONE), 'cafe-b')->json('customer.id');

        $this->assertNotSame($a, $b);
        $this->assertSame(2, app(TenantContext::class)->bypass(fn () => Customer::query()->where('phone_e164', self::PHONE)->count()));
    }

    public function test_code_issued_for_one_tenant_is_invalid_at_another(): void
    {
        $this->requestCode('cafe-a');

        $this->verify((string) $this->sms->lastCodeFor(self::PHONE), 'cafe-b')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_expired');
    }

    public function test_wrong_codes_lock_the_code_after_max_attempts(): void
    {
        $this->requestCode();
        $real = (string) $this->sms->lastCodeFor(self::PHONE);
        $wrong = $real === '00000' ? '11111' : '00000';

        foreach (range(1, 4) as $ignored) {
            $this->verify($wrong)->assertUnprocessable()->assertJsonPath('code', 'otp_invalid');
        }

        $this->verify($wrong)->assertStatus(429)->assertJsonPath('code', 'otp_locked');
        // Even the correct code no longer works: the attacker must request a new one (cooldown + daily cap apply).
        $this->verify($real)->assertUnprocessable()->assertJsonPath('code', 'otp_expired');
    }

    public function test_code_expires(): void
    {
        $this->requestCode();
        $code = (string) $this->sms->lastCodeFor(self::PHONE);

        $this->travel(121)->seconds();

        $this->verify($code)->assertUnprocessable()->assertJsonPath('code', 'otp_expired');
    }

    public function test_resend_cooldown_and_daily_limit(): void
    {
        $this->requestCode()->assertOk();
        $this->requestCode()->assertStatus(429)->assertJsonPath('code', 'otp_cooldown');

        config(['otp.daily_limit_per_phone' => 2]);
        $this->travel(61)->seconds();
        $this->requestCode()->assertOk();
        $this->travel(61)->seconds();
        $this->requestCode()->assertStatus(429)->assertJsonPath('code', 'otp_daily_limit');
    }

    public function test_invalid_phone_gets_a_persian_validation_error(): void
    {
        $this->requestCode(phone: '02188776655')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'اطلاعات واردشده صحیح نیست. لطفاً موارد مشخص‌شده را بررسی کنید.')
            ->assertJsonPath('errors.phone.0', 'شماره موبایل باید یک شماره موبایل معتبر ایران باشد (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).');
    }

    public function test_otp_requests_are_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->requestCode(phone: '0912000000'.$i);
        }

        $this->requestCode(phone: '09120000009')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_otp_needs_a_tenant(): void
    {
        $this->postJson('/api/v1/auth/customer/otp/request', ['phone' => '09121234567'])->assertNotFound();
        $this->assertSame([], $this->sms->verificationCodes);
    }
}
