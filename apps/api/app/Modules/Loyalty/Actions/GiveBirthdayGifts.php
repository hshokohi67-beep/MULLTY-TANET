<?php

namespace App\Modules\Loyalty\Actions;

use App\Modules\Core\Support\TenantSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Enums\PointsTransactionType;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Support\Localization\JalaliDate;
use App\Support\Localization\PersianNumber;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use App\Support\Sms\SmsMessage;
use App\Support\Sms\SmsProvider;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Birthday gifts for the current tenant, on the customer's Jalali birthday in the tenant's local
 * time, from 09:00. Once per customer per Jalali year (the idempotency keys make re-runs harmless).
 * People born on Esfand 30 celebrate on Esfand 29 in non-leap years.
 */
final class GiveBirthdayGifts
{
    public const LOCAL_HOUR = 9;

    public function __construct(
        private readonly PostWalletTransaction $wallet,
        private readonly PostPointsTransaction $points,
        private readonly SmsProvider $sms,
    ) {}

    /** @return int number of customers gifted this run */
    public function handle(?CarbonImmutable $now = null): int
    {
        $tenant = app(TenantContext::class)->require();
        $local = ($now ?? CarbonImmutable::now())->setTimezone($tenant->timezone);

        $gift = (int) TenantSettings::get('loyalty.birthday_wallet_gift');
        $points = (int) TenantSettings::get('loyalty.birthday_points');

        if (! TenantSettings::get('loyalty.enabled') || ($gift <= 0 && $points <= 0) || $local->hour < self::LOCAL_HOUR) {
            return 0;
        }

        ['year' => $year, 'month' => $month, 'day' => $day] = JalaliDate::toJalali($local, $tenant->timezone);
        $days = [$day];
        if ($month === 12 && $day === 29 && ! JalaliDate::isLeapYear($year)) {
            $days[] = 30;
        }

        $count = 0;
        Customer::query()->where('birth_month', $month)->whereIn('birth_day', $days)->orderBy('id')
            ->each(function (Customer $customer) use ($year, $gift, $points, $tenant, &$count): void {
                $given = false;

                if ($gift > 0) {
                    $given = $this->wallet->handle($customer, WalletTransactionType::Birthday, $gift, "birthday:{$customer->id}:{$year}:wallet", 'هدیه‌ی تولد')->wasRecentlyCreated;
                }

                if ($points > 0) {
                    $given = $this->points->handle($customer, PointsTransactionType::Birthday, $points, "birthday:{$customer->id}:{$year}:points", 'هدیه‌ی تولد')->wasRecentlyCreated || $given;
                }

                if (! $given) {
                    return; // already celebrated this year
                }

                $count++;

                try {
                    $this->sms->send(new SmsMessage([$customer->phone_e164], __('messages.birthday_sms', [
                        'name' => $customer->name ?: 'دوست عزیز',
                        'cafe' => $tenant->name,
                        'gift' => $gift > 0 ? MoneyFormatter::format(Money::rials($gift)) : PersianNumber::toPersian((string) $points).' امتیاز',
                    ])));
                } catch (Throwable $e) {
                    report($e); // the gift stands even if the SMS fails
                }
            });

        return $count;
    }
}
