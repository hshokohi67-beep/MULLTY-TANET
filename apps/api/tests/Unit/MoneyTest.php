<?php

namespace Tests\Unit;

use App\Support\Money\CurrencyUnit;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_toman_input_is_stored_as_rial(): void
    {
        $this->assertSame(1_250_000, Money::fromUnit(125_000, CurrencyUnit::Toman)->rials);
        $this->assertSame(125_000, Money::fromUnit(125_000, CurrencyUnit::Rial)->rials);
    }

    public function test_arithmetic_is_integer_only(): void
    {
        $price = Money::rials(1_250_000);

        $this->assertSame(2_500_000, $price->times(2)->rials);
        $this->assertSame(1_000_000, $price->minus(Money::rials(250_000))->rials);
        $this->assertSame(156_250, $price->percentage(1250)->rials); // 12.5%
        $this->assertSame(1, Money::rials(5)->percentage(1000)->rials); // 0.5 rounds half-up
    }

    public function test_overflow_is_detected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::rials(PHP_INT_MAX)->plus(Money::rials(1));
    }

    public function test_formatting_in_persian(): void
    {
        $this->assertSame('۱۲۵٬۰۰۰ تومان', MoneyFormatter::format(Money::rials(1_250_000)));
        $this->assertSame('۱٬۲۵۰٬۰۰۰ ریال', MoneyFormatter::format(Money::rials(1_250_000), CurrencyUnit::Rial));
        $this->assertSame('۱۲۵٬۰۰۰٫۵ تومان', MoneyFormatter::format(Money::rials(1_250_005)));
        $this->assertSame('−۵۰ تومان', MoneyFormatter::format(Money::rials(-500)));
        $this->assertSame('۰', MoneyFormatter::format(Money::zero(), withUnit: false));
    }
}
