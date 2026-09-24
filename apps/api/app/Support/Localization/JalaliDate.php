<?php

namespace App\Support\Localization;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlCalendar;
use IntlDateFormatter;
use InvalidArgumentException;

/**
 * Jalali (Solar Hijri) conversion and formatting, backed by ICU's persian calendar.
 * One implementation for the whole backend (the legacy code had two that disagreed).
 *
 * Storage is always UTC/Gregorian. Conversion happens only here and in the frontend formatter.
 */
final class JalaliDate
{
    /**
     * Format an instant in the given timezone. Pattern is ICU syntax, e.g. 'yyyy/MM/dd', 'd MMMM y', 'EEEE'.
     */
    public static function format(DateTimeInterface $date, string $pattern = 'yyyy/MM/dd', string $timezone = 'Asia/Tehran', bool $persianDigits = true): string
    {
        $formatter = new IntlDateFormatter(
            $persianDigits ? 'fa_IR@calendar=persian' : 'en_US@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $timezone,
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );

        $result = $formatter->format($date);

        if ($result === false) {
            throw new InvalidArgumentException('Unable to format date as Jalali.');
        }

        return $result;
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    public static function toJalali(DateTimeInterface $date, string $timezone = 'Asia/Tehran'): array
    {
        [$year, $month, $day] = array_map('intval', explode('/', self::format($date, 'y/M/d', $timezone, persianDigits: false)));

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    /**
     * Start of the given Jalali day in the given timezone, returned as an immutable instance in that timezone.
     */
    public static function toGregorian(int $year, int $month, int $day, string $timezone = 'Asia/Tehran'): CarbonImmutable
    {
        if (! self::isValid($year, $month, $day)) {
            throw new InvalidArgumentException(sprintf('Invalid Jalali date %d/%d/%d.', $year, $month, $day));
        }

        $calendar = IntlCalendar::createInstance($timezone, 'fa_IR@calendar=persian');
        $calendar->clear();
        $calendar->setDateTime($year, $month - 1, $day, 0, 0, 0);

        return CarbonImmutable::createFromTimestamp((int) floor($calendar->getTime() / 1000), $timezone);
    }

    public static function isValid(int $year, int $month, int $day): bool
    {
        if ($year < 1 || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        return $day <= self::daysInMonth($year, $month);
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return self::isLeapYear($year) ? 30 : 29;
    }

    public static function isLeapYear(int $year): bool
    {
        $calendar = IntlCalendar::createInstance('UTC', 'fa_IR@calendar=persian');
        $calendar->clear();
        $calendar->setDate($year, 11, 1);

        return $calendar->getActualMaximum(IntlCalendar::FIELD_DAY_OF_MONTH) === 30;
    }
}
