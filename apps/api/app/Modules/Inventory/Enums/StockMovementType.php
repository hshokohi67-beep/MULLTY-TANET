<?php

namespace App\Modules\Inventory\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReversal = 'sale_reversal';
    case Adjustment = 'adjustment';
    case Waste = 'waste';
    case Count = 'count';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'خرید',
            self::Sale => 'فروش',
            self::SaleReversal => 'برگشت فروش',
            self::Adjustment => 'اصلاح موجودی',
            self::Waste => 'ضایعات',
            self::Count => 'انبارگردانی',
        };
    }
}
