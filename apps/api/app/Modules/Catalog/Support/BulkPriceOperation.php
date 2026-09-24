<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Exceptions\InvalidPriceException;

/**
 * Pure price arithmetic for bulk changes. All amounts are integer rial.
 */
enum BulkPriceOperation: string
{
    case PercentIncrease = 'percent_increase';
    case PercentDecrease = 'percent_decrease';
    case FixedIncrease = 'fixed_increase';
    case FixedDecrease = 'fixed_decrease';
    case Exact = 'exact';

    public function label(): string
    {
        return match ($this) {
            self::PercentIncrease => 'افزایش درصدی',
            self::PercentDecrease => 'کاهش درصدی',
            self::FixedIncrease => 'افزایش مبلغ ثابت',
            self::FixedDecrease => 'کاهش مبلغ ثابت',
            self::Exact => 'قیمت دقیق',
        };
    }

    /**
     * @param  int  $value  basis points for percent operations (1250 = 12.5%), rial otherwise
     * @param  int  $roundTo  round the result to a multiple of this many rial (0 = no rounding), half up
     */
    public function apply(int $current, int $value, int $roundTo = 0): int
    {
        $result = match ($this) {
            self::PercentIncrease => $current + intdiv($current * $value + 5000, 10000),
            self::PercentDecrease => $current - intdiv($current * $value + 5000, 10000),
            self::FixedIncrease => $current + $value,
            self::FixedDecrease => $current - $value,
            self::Exact => $value,
        };

        if ($roundTo > 0) {
            $result = intdiv($result + intdiv($roundTo, 2), $roundTo) * $roundTo;
        }

        if ($result < 0) {
            throw InvalidPriceException::negative();
        }

        return $result;
    }
}
