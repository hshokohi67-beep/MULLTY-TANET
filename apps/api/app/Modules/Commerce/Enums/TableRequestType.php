<?php

namespace App\Modules\Commerce\Enums;

enum TableRequestType: string
{
    case CallWaiter = 'call_waiter';
    case RequestBill = 'request_bill';

    public function label(): string
    {
        return match ($this) {
            self::CallWaiter => 'صدا زدن گارسون',
            self::RequestBill => 'درخواست صورت‌حساب',
        };
    }
}
