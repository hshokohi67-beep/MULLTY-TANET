<?php

namespace App\Support\Localization;

/**
 * Digit conversion between Persian (۰-۹), Arabic-Indic (٠-٩) and Latin (0-9).
 * Only used at the edges (input normalisation, presentation). Storage is always Latin/numeric.
 */
final class PersianNumber
{
    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function toLatin(string $value): string
    {
        return str_replace([...self::PERSIAN, ...self::ARABIC], [...self::LATIN, ...self::LATIN], $value);
    }

    public static function toPersian(string $value): string
    {
        return str_replace([...self::LATIN, ...self::ARABIC], [...self::PERSIAN, ...self::PERSIAN], $value);
    }

    /**
     * 1250000 → "۱٬۲۵۰٬۰۰۰" (Persian thousands separator U+066C).
     */
    public static function format(int|float $number, int $decimals = 0): string
    {
        $formatted = number_format($number, $decimals, '٫', '٬');

        return self::toPersian($formatted);
    }
}
