<?php

namespace App\Modules\Discounts\Enums;

enum DiscountKind: string
{
    case Percent = 'percent'; // value = basis points (1000 = 10%)
    case Fixed = 'fixed';     // value = rial

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'درصدی',
            self::Fixed => 'مبلغ ثابت',
        };
    }
}
