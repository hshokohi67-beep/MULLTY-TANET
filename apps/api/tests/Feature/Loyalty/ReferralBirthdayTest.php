<?php

namespace Tests\Feature\Loyalty;

use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Actions\GiveBirthdayGifts;
use App\Support\Localization\JalaliDate;
use App\Support\Sms\Providers\ArraySmsProvider;
use App\Support\Sms\SmsProvider;
use Carbon\CarbonImmutable;

final class ReferralBirthdayTest extends ClubTestCase
{
    public function test_referral_is_rewarded_on_the_first_completed_order_only(): void
    {
        $this->settings(['loyalty.referral_referrer_reward' => '200000', 'loyalty.referral_referee_reward' => '100000']);
        [$friend] = $this->customer('+989120000100');
        [$newbie, $token] = $this->customer('+989120000200');
        $headers = $this->publicHeaders($this->auth($token));

        $this->postJson('/api/v1/customer/referral', ['code' => 'NOPE123'], $headers)->assertUnprocessable()->assertJsonPath('code', 'referral_invalid');
        $this->postJson('/api/v1/customer/referral', ['code' => $newbie->referral_code], $headers)->assertJsonPath('code', 'referral_invalid');
        $this->postJson('/api/v1/customer/referral', ['code' => strtolower((string) $friend->referral_code)], $headers)->assertOk();
        $this->postJson('/api/v1/customer/referral', ['code' => $friend->referral_code], $headers)->assertJsonPath('code', 'referral_already_set');

        // Placing an order is not enough; completing it is.
        $order = $this->customerOrder($token);
        $this->assertSame(0, $this->walletBalance($friend));
        $this->complete($order);
        $this->assertSame(200_000, $this->walletBalance($friend));
        $this->assertSame(100_000, $this->walletBalance($newbie));

        $this->complete($this->customerOrder($token));
        $this->assertSame(200_000, $this->walletBalance($friend));
    }

    public function test_referral_codes_expire_after_the_first_week_or_first_purchase(): void
    {
        [$friend] = $this->customer('+989120000100');
        [, $lateToken] = $this->customer('+989120000300');
        [, $buyerToken] = $this->customer('+989120000400');

        $this->complete($this->customerOrder($buyerToken));
        $this->postJson('/api/v1/customer/referral', ['code' => $friend->referral_code], $this->publicHeaders($this->auth($buyerToken)))->assertJsonPath('code', 'referral_not_allowed');

        $this->travel(8)->days();
        $this->postJson('/api/v1/customer/referral', ['code' => $friend->referral_code], $this->publicHeaders($this->auth($lateToken)))->assertJsonPath('code', 'referral_not_allowed');
    }

    private function birthdayOn(int $month, int $day, string $phone = '+989121111111'): Customer
    {
        [$customer] = $this->customer($phone);
        $this->inTenant($this->tenant, fn () => $customer->forceFill(['birth_month' => $month, 'birth_day' => $day])->save());

        return $customer;
    }

    private function gifts(CarbonImmutable $at): int
    {
        return $this->inTenant($this->tenant, fn () => app(GiveBirthdayGifts::class)->handle($at));
    }

    public function test_birthday_gift_on_the_jalali_day_after_nine_local_once_a_year(): void
    {
        $this->settings(['loyalty.birthday_wallet_gift' => '500000', 'loyalty.birthday_points' => '20']);
        $customer = $this->birthdayOn(7, 2);
        $this->birthdayOn(7, 3, '+989122222222');
        $day = JalaliDate::toGregorian(1405, 7, 2, 'Asia/Tehran');

        $this->assertSame(0, $this->gifts($day->setTime(8, 59)));
        $this->assertSame(1, $this->gifts($day->setTime(9, 0)));
        $this->assertSame(0, $this->gifts($day->setTime(15, 0)));

        $this->assertSame(500_000, $this->walletBalance($customer));
        $this->assertSame(20, $this->points($customer));
        $sms = app(SmsProvider::class);
        $this->assertInstanceOf(ArraySmsProvider::class, $sms);
        $this->assertStringContainsString('تولدت مبارک', $sms->messages[0]->text);
        $this->assertStringContainsString('۵۰٬۰۰۰ تومان', $sms->messages[0]->text);

        // Next Jalali year: again.
        $this->assertSame(1, $this->gifts(JalaliDate::toGregorian(1406, 7, 2, 'Asia/Tehran')->setTime(10, 0)));
        $this->assertSame(1_000_000, $this->walletBalance($customer));
    }

    public function test_esfand_30_birthdays_are_celebrated_on_esfand_29_in_common_years(): void
    {
        $this->settings(['loyalty.birthday_wallet_gift' => '100000']);
        $customer = $this->birthdayOn(12, 30);
        $common = JalaliDate::isLeapYear(1404) ? 1405 : 1404;

        $this->assertSame(1, $this->gifts(JalaliDate::toGregorian($common, 12, 29, 'Asia/Tehran')->setTime(12, 0)));
        $this->assertSame(100_000, $this->walletBalance($customer));
    }

    public function test_no_gifts_while_the_club_is_off(): void
    {
        $this->settings(['loyalty.birthday_wallet_gift' => '100000', 'loyalty.enabled' => '0']);
        $this->birthdayOn(7, 2);

        $this->assertSame(0, $this->gifts(JalaliDate::toGregorian(1405, 7, 2, 'Asia/Tehran')->setTime(12, 0)));
    }

    public function test_customers_set_their_birthday_once_and_staff_can_fix_it(): void
    {
        [$customer, $token] = $this->customer();
        $headers = $this->publicHeaders($this->auth($token));

        $this->patchJson('/api/v1/customer/profile', ['birth_month' => 13, 'birth_day' => 1], $headers)->assertJsonValidationErrors('birth_month');
        $this->patchJson('/api/v1/customer/profile', ['birth_month' => 7, 'birth_day' => 31], $headers)->assertUnprocessable()->assertJsonPath('code', 'birthday_invalid');
        $this->patchJson('/api/v1/customer/profile', ['name' => 'سارا', 'birth_month' => '۷', 'birth_day' => '۲'], $headers)->assertOk()
            ->assertJsonPath('data.birth_month', 7)
            ->assertJsonPath('data.birthday_locked', true);
        $this->patchJson('/api/v1/customer/profile', ['birth_month' => 7, 'birth_day' => 3], $headers)->assertJsonPath('code', 'birthday_locked');
        // Sending the same birthday again (a full-form save) is fine.
        $this->patchJson('/api/v1/customer/profile', ['name' => 'سارا ن.', 'birth_month' => 7, 'birth_day' => 2], $headers)->assertOk();
        // A customer can't write a staff note.
        $this->patchJson('/api/v1/customer/profile', ['staff_note' => 'VIP'], $headers)->assertOk();
        $this->assertNull($customer->fresh()?->staff_note);

        $this->staff('PATCH', "/api/v1/customers/{$customer->id}", ['birth_month' => 7, 'birth_day' => 3, 'staff_note' => 'مشتری ثابت صبح‌ها'])->assertOk();
        $this->getJson('/api/v1/customer/profile', $headers)->assertJsonPath('data.birth_day', 3)->assertJsonPath('data.name', 'سارا ن.');
    }
}
