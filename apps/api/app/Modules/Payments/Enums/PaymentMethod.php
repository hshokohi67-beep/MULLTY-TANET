<?php

namespace App\Modules\Payments\Enums;

enum PaymentMethod: string
{
    case Online = 'online';
    case Cash = 'cash';
    case CardPos = 'card_pos';
    case Other = 'other';
    case Wallet = 'wallet'; // club wallet (Loyalty module)

    public function label(): string
    {
        return match ($this) {
            self::Online => 'پرداخت اینترنتی',
            self::Cash => 'نقدی',
            self::CardPos => 'کارتخوان',
            self::Other => 'سایر',
            self::Wallet => 'کیف پول باشگاه',
        };
    }

    /**
     * Methods a staff member records by hand at the counter.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Cash, self::CardPos, self::Other];
    }
}
