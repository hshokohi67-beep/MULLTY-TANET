<?php

namespace Tests\Unit\Localization;

use App\Support\Localization\JalaliDate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JalaliDateTest extends TestCase
{
    public function test_formats_gregorian_utc_instant_as_jalali_in_tehran(): void
    {
        $instant = CarbonImmutable::parse('2026-09-24 10:00:00', 'UTC');

        $this->assertSame('۱۴۰۵/۰۷/۰۲', JalaliDate::format($instant));
        $this->assertSame('1405/07/02', JalaliDate::format($instant, persianDigits: false));
        $this->assertSame('۲ مهر ۱۴۰۵', JalaliDate::format($instant, 'd MMMM y'));
    }

    public function test_timezone_decides_the_day(): void
    {
        // 21:00 UTC on 2026-09-23 is already 00:30 on 1405/07/02 in Tehran.
        $instant = CarbonImmutable::parse('2026-09-23 21:00:00', 'UTC');

        $this->assertSame(['year' => 1405, 'month' => 7, 'day' => 2], JalaliDate::toJalali($instant));
        $this->assertSame(['year' => 1405, 'month' => 7, 'day' => 1], JalaliDate::toJalali($instant, 'UTC'));
    }

    public function test_round_trip_jalali_to_gregorian(): void
    {
        $this->assertSame('2026-09-24', JalaliDate::toGregorian(1405, 7, 2)->toDateString());
        $this->assertSame('2024-03-20', JalaliDate::toGregorian(1403, 1, 1)->toDateString()); // Nowruz 1403
        $this->assertSame('2025-03-20', JalaliDate::toGregorian(1403, 12, 30)->toDateString()); // leap day
    }

    public function test_leap_years_and_month_lengths(): void
    {
        $this->assertTrue(JalaliDate::isLeapYear(1403));
        $this->assertFalse(JalaliDate::isLeapYear(1404));
        $this->assertSame(31, JalaliDate::daysInMonth(1405, 6));
        $this->assertSame(30, JalaliDate::daysInMonth(1405, 7));
        $this->assertSame(29, JalaliDate::daysInMonth(1404, 12));
        $this->assertFalse(JalaliDate::isValid(1404, 12, 30));
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JalaliDate::toGregorian(1404, 12, 30);
    }
}
