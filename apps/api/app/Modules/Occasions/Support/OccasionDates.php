<?php

namespace App\Modules\Occasions\Support;

use App\Modules\Occasions\Enums\OccasionCalendar;
use App\Modules\Occasions\Models\Occasion;
use App\Support\Localization\JalaliDate;
use Carbon\CarbonImmutable;
use IntlCalendar;

/**
 * Turns an occasion and a year of its own calendar into Gregorian days.
 *
 * Hijri dates use ICU's civil Islamic calendar computed in UTC. Checked against Iran's official
 * calendar it matches most dates exactly and is at most a day off (moon sighting), which is why
 * Hijri occurrences stay «تقریبی» until a platform admin confirms them.
 */
final class OccasionDates
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null start and end day (inclusive) */
    public static function occurrence(Occasion $occasion, int $year): ?array
    {
        $start = match (true) {
            $occasion->rule === 'chaharshanbe_suri' => self::chaharshanbeSuri($year),
            $occasion->rule === 'black_friday' => self::blackFriday($year),
            $occasion->month === null || $occasion->day === null => null,
            $occasion->calendar === OccasionCalendar::Jalali => self::jalali($year, $occasion->month, $occasion->day),
            $occasion->calendar === OccasionCalendar::Hijri => self::hijri($year, $occasion->month, $occasion->day),
            default => checkdate($occasion->month, $occasion->day, $year) ? CarbonImmutable::create($year, $occasion->month, $occasion->day, 0, 0, 0, 'UTC') : null,
        };

        return $start === null ? null : [$start, $start->addDays(max(1, $occasion->duration_days) - 1)];
    }

    /** The year «now» falls in, in the given calendar. */
    public static function currentYear(OccasionCalendar $calendar, CarbonImmutable $today): int
    {
        return match ($calendar) {
            OccasionCalendar::Jalali => JalaliDate::toJalali($today)['year'],
            OccasionCalendar::Hijri => self::hijriYear($today),
            OccasionCalendar::Gregorian => (int) $today->format('Y'),
        };
    }

    private static function jalali(int $year, int $month, int $day): ?CarbonImmutable
    {
        if (! JalaliDate::isValid($year, $month, $day)) {
            return null;
        }

        return CarbonImmutable::parse(JalaliDate::toGregorian($year, $month, $day)->format('Y-m-d'), 'UTC');
    }

    private static function hijri(int $year, int $month, int $day): CarbonImmutable
    {
        $calendar = self::islamic();
        $calendar->clear();
        $calendar->setDate($year, $month - 1, $day);

        return CarbonImmutable::createFromTimestampUTC((int) floor($calendar->getTime() / 1000))->startOfDay();
    }

    private static function hijriYear(CarbonImmutable $day): int
    {
        $calendar = self::islamic();
        $calendar->setTime((float) $day->copy()->utc()->startOfDay()->getTimestamp() * 1000);

        return $calendar->get(IntlCalendar::FIELD_YEAR);
    }

    private static function islamic(): IntlCalendar
    {
        return IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-civil');
    }

    /** The evening before the last Wednesday of the Jalali year (Tuesday night). */
    private static function chaharshanbeSuri(int $jalaliYear): CarbonImmutable
    {
        $nowruz = self::jalali($jalaliYear + 1, 1, 1) ?? throw new \RuntimeException('Invalid year');
        $day = $nowruz->subDay();
        while ($day->dayOfWeekIso !== 3) {
            $day = $day->subDay();
        }

        return $day->subDay();
    }

    /** The day after the fourth Thursday of November. */
    private static function blackFriday(int $year): CarbonImmutable
    {
        $day = CarbonImmutable::create($year, 11, 1, 0, 0, 0, 'UTC');
        while ($day->dayOfWeekIso !== 4) {
            $day = $day->addDay();
        }

        return $day->addWeeks(3)->addDay();
    }
}
