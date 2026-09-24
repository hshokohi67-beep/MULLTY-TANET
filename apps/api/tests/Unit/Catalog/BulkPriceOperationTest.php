<?php

namespace Tests\Unit\Catalog;

use App\Modules\Catalog\Exceptions\InvalidPriceException;
use App\Modules\Catalog\Support\BulkPriceOperation as Op;
use App\Modules\Catalog\Support\Slugger;
use PHPUnit\Framework\TestCase;

final class BulkPriceOperationTest extends TestCase
{
    public function test_operations_on_integer_rial(): void
    {
        $this->assertSame(1_100_000, Op::PercentIncrease->apply(1_000_000, 1000));   // +10%
        $this->assertSame(875_000, Op::PercentDecrease->apply(1_000_000, 1250));     // −12.5%
        $this->assertSame(1_200_000, Op::FixedIncrease->apply(1_000_000, 200_000));
        $this->assertSame(800_000, Op::FixedDecrease->apply(1_000_000, 200_000));
        $this->assertSame(750_000, Op::Exact->apply(1_000_000, 750_000));
    }

    public function test_rounding_to_a_cafe_friendly_amount(): void
    {
        // 850,000 rial +7% = 909,500 → nearest 10,000 rial (1,000 toman) = 910,000.
        $this->assertSame(910_000, Op::PercentIncrease->apply(850_000, 700, 10_000));
        $this->assertSame(900_000, Op::PercentIncrease->apply(850_000, 550, 50_000)); // 896,750 → 900,000
    }

    public function test_percent_uses_half_up_rounding_without_floats(): void
    {
        $this->assertSame(1_001, Op::PercentIncrease->apply(1_000, 5)); // 0.05% of 1000 = 0.5 → rounds up
    }

    public function test_negative_results_are_refused(): void
    {
        $this->expectException(InvalidPriceException::class);
        Op::FixedDecrease->apply(100_000, 200_000);
    }

    public function test_persian_slugs(): void
    {
        $this->assertSame('لاته-وانیلی', Slugger::make(' لاته   وانیلی! '));
        $this->assertSame('کیک-2-نفره', Slugger::make("كيک\u{200C}۲ نفره"));
        $this->assertSame('iced-latte', Slugger::make('Iced Latte'));
        $this->assertSame('item', Slugger::make('!!!'));
        $this->assertSame('لاته-3', Slugger::unique('لاته', fn (string $s) => in_array($s, ['لاته', 'لاته-2'], true)));
    }
}
