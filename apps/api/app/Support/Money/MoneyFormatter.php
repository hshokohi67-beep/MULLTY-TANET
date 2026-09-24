<?php

namespace App\Support\Money;

use App\Support\Localization\PersianNumber;

/**
 * The only place backend output (SMS, PDF, exports) turns Money into text.
 * API responses return raw integer rials; the frontend has its own formatter in packages/locale.
 */
final class MoneyFormatter
{
    public static function format(Money $money, CurrencyUnit $unit = CurrencyUnit::Toman, bool $withUnit = true): string
    {
        $factor = $unit->rialFactor();
        $negative = $money->rials < 0;
        $abs = abs($money->rials);

        $whole = intdiv($abs, $factor);
        $remainder = $abs % $factor;

        $text = PersianNumber::format($whole);

        if ($remainder !== 0) {
            // Only possible for toman when the rial amount isn't a multiple of 10: show one decimal.
            $text .= '٫'.PersianNumber::toPersian((string) $remainder);
        }

        if ($negative) {
            $text = '−'.$text;
        }

        return $withUnit ? $text.' '.$unit->label() : $text;
    }
}
