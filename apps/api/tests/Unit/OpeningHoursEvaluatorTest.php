<?php

namespace Tests\Unit;

use App\Modules\Core\Support\OpeningHoursEvaluator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class OpeningHoursEvaluatorTest extends TestCase
{
    private function evaluator(): OpeningHoursEvaluator
    {
        return new OpeningHoursEvaluator([
            ['weekday' => 4, 'opens_at' => '08:00', 'closes_at' => '23:00'], // Thursday
            ['weekday' => 5, 'opens_at' => '16:00', 'closes_at' => '01:00'], // Friday, runs past midnight
            ['weekday' => 6, 'opens_at' => '08:00', 'closes_at' => '12:00'], // Saturday, split shift
            ['weekday' => 6, 'opens_at' => '17:00', 'closes_at' => '22:00'],
        ], 'Asia/Tehran');
    }

    private function tehran(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, 'Asia/Tehran');
    }

    public function test_open_and_closed_within_a_day(): void
    {
        // 2026-09-24 is a Thursday.
        $this->assertTrue($this->evaluator()->isOpenAt($this->tehran('2026-09-24 08:00')));
        $this->assertTrue($this->evaluator()->isOpenAt($this->tehran('2026-09-24 22:59')));
        $this->assertFalse($this->evaluator()->isOpenAt($this->tehran('2026-09-24 23:00')));
        $this->assertFalse($this->evaluator()->isOpenAt($this->tehran('2026-09-24 07:59')));
    }

    public function test_overnight_interval_continues_into_the_next_day(): void
    {
        $this->assertTrue($this->evaluator()->isOpenAt($this->tehran('2026-09-25 23:30'))); // Friday night
        $this->assertTrue($this->evaluator()->isOpenAt($this->tehran('2026-09-26 00:30'))); // Saturday 00:30, still Friday's shift
        $this->assertFalse($this->evaluator()->isOpenAt($this->tehran('2026-09-26 01:00')));
    }

    public function test_split_shift(): void
    {
        $this->assertTrue($this->evaluator()->isOpenAt($this->tehran('2026-09-26 11:00')));
        $this->assertFalse($this->evaluator()->isOpenAt($this->tehran('2026-09-26 14:00')));
        $this->assertTrue($this->evaluator()->isOpenAt($this->tehran('2026-09-26 18:00')));
    }

    public function test_instants_in_other_timezones_are_converted(): void
    {
        // 05:00 UTC Thursday = 08:30 Tehran.
        $this->assertTrue($this->evaluator()->isOpenAt(CarbonImmutable::parse('2026-09-24 05:00', 'UTC')));
    }

    public function test_next_opening(): void
    {
        $next = $this->evaluator()->nextOpeningAfter($this->tehran('2026-09-26 14:00'));
        $this->assertSame('2026-09-26 17:00', $next?->format('Y-m-d H:i'));

        // Saturday night → next is Thursday 08:00.
        $next = $this->evaluator()->nextOpeningAfter($this->tehran('2026-09-26 23:00'));
        $this->assertSame('2026-10-01 08:00', $next?->format('Y-m-d H:i'));
    }

    public function test_no_schedule_means_closed(): void
    {
        $evaluator = new OpeningHoursEvaluator([], 'Asia/Tehran');

        $this->assertFalse($evaluator->isOpenAt(CarbonImmutable::now()));
        $this->assertNull($evaluator->nextOpeningAfter(CarbonImmutable::now()));
    }
}
