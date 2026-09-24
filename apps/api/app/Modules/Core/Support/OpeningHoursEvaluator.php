<?php

namespace App\Modules\Core\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Pure logic: is a branch open at a given instant, and when does it next open?
 *
 * Intervals are local wall-clock times in the tenant timezone, keyed by ISO weekday.
 * An interval with closes_at <= opens_at runs past midnight (e.g. 18:00 → 02:00).
 * Legacy did not support this; cafes open late at night need it.
 */
final class OpeningHoursEvaluator
{
    /**
     * @param  iterable<array{weekday: int, opens_at: string, closes_at: string}>  $intervals
     */
    public function __construct(private readonly iterable $intervals, private readonly string $timezone) {}

    public function isOpenAt(DateTimeInterface $instant): bool
    {
        $now = CarbonImmutable::instance($instant)->setTimezone($this->timezone);

        foreach ($this->occurrencesAround($now) as [$start, $end]) {
            if ($now->greaterThanOrEqualTo($start) && $now->lessThan($end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Next moment the branch opens (strictly after $instant), or null if there are no intervals.
     */
    public function nextOpeningAfter(DateTimeInterface $instant): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($instant)->setTimezone($this->timezone);
        $next = null;

        for ($dayOffset = 0; $dayOffset <= 7; $dayOffset++) {
            $day = $now->startOfDay()->addDays($dayOffset);

            foreach ($this->intervalsFor($day->isoWeekday()) as $interval) {
                $start = $this->at($day, $interval['opens_at']);

                if ($start->greaterThan($now) && ($next === null || $start->lessThan($next))) {
                    $next = $start;
                }
            }

            if ($next !== null) {
                return $next;
            }
        }

        return null;
    }

    /**
     * Concrete [start, end) instants for intervals starting yesterday and today
     * (yesterday's overnight interval may still be running).
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function occurrencesAround(CarbonImmutable $now): array
    {
        $occurrences = [];

        foreach ([$now->startOfDay()->subDay(), $now->startOfDay()] as $day) {
            foreach ($this->intervalsFor($day->isoWeekday()) as $interval) {
                $start = $this->at($day, $interval['opens_at']);
                $end = $this->at($day, $interval['closes_at']);

                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }

                $occurrences[] = [$start, $end];
            }
        }

        return $occurrences;
    }

    /** @return list<array{weekday: int, opens_at: string, closes_at: string}> */
    private function intervalsFor(int $isoWeekday): array
    {
        $result = [];

        foreach ($this->intervals as $interval) {
            if ((int) $interval['weekday'] === $isoWeekday) {
                $result[] = $interval;
            }
        }

        return $result;
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->setTime($hour, $minute);
    }
}
