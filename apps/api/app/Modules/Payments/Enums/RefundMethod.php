<?php

namespace App\Modules\Payments\Enums;

enum RefundMethod: string
{
    case GatewayPanel = 'gateway_panel';
    case Cash = 'cash';
    case Card = 'card';
    case Other = 'other';
    case Wallet = 'wallet'; // back into the club wallet (the only way to refund a wallet payment)

    public function label(): string
    {
        return match ($this) {
            self::GatewayPanel => 'از پنل درگاه',
            self::Cash => 'نقدی',
            self::Card => 'کارت‌به‌کارت',
            self::Other => 'سایر',
            self::Wallet => 'به کیف پول',
        };
    }
}
