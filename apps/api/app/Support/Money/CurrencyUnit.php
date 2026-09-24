<?php

namespace App\Support\Money;

/**
 * Display units for Iranian money. Storage is always integer rial;
 * the unit only affects presentation and user input.
 */
enum CurrencyUnit: string
{
    case Rial = 'rial';
    case Toman = 'toman';

    public function label(): string
    {
        return match ($this) {
            self::Rial => 'ریال',
            self::Toman => 'تومان',
        };
    }

    /** How many rials make one of this unit. */
    public function rialFactor(): int
    {
        return match ($this) {
            self::Rial => 1,
            self::Toman => 10,
        };
    }
}
