<?php

namespace App\Modules\Loyalty\Enums;

enum PointsTransactionType: string
{
    case Earn = 'earn';
    case EarnReversal = 'earn_reversal';
    case Redeem = 'redeem';
    case Birthday = 'birthday';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'امتیاز خرید',
            self::EarnReversal => 'برگشت امتیاز خرید',
            self::Redeem => 'تبدیل به کیف پول',
            self::Birthday => 'هدیه‌ی تولد',
            self::Adjustment => 'اصلاح دستی',
        };
    }
}
