<?php

namespace App\Modules\Catalog\Enums;

enum AvailabilityStatus: string
{
    case Available = 'available';
    case SoldOut = 'sold_out';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'موجود',
            self::SoldOut => 'تمام شد',
            self::Hidden => 'پنهان',
        };
    }
}
