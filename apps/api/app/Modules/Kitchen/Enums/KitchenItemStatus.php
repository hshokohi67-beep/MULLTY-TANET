<?php

namespace App\Modules\Kitchen\Enums;

enum KitchenItemStatus: string
{
    case Queued = 'queued';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'در صف',
            self::Preparing => 'در حال آماده‌سازی',
            self::Ready => 'آماده',
            self::Cancelled => 'لغو شد',
        };
    }

    public function canBecome(self $next): bool
    {
        return match ($this) {
            self::Queued => in_array($next, [self::Preparing, self::Ready, self::Cancelled], true),
            self::Preparing => in_array($next, [self::Ready, self::Cancelled], true),
            self::Ready => in_array($next, [self::Preparing, self::Cancelled], true), // recall
            self::Cancelled => false,
        };
    }
}
