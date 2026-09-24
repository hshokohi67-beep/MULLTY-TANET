<?php

namespace App\Modules\Commerce\Enums;

/** Where the order came from, independent of its type (e.g. delivery + marketplace). */
enum OrderSource: string
{
    case Web = 'web';
    case Qr = 'qr';
    case Dashboard = 'dashboard';
    case Marketplace = 'marketplace';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'سایت',
            self::Qr => 'کیوآر میز',
            self::Dashboard => 'پنل',
            self::Marketplace => 'بازارگاه',
            self::Phone => 'تلفن',
        };
    }
}
