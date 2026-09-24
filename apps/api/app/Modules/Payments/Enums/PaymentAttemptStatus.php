<?php

namespace App\Modules\Payments\Enums;

/** Status of one payment (attempt). The order's overall status is derived from all of them. */
enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Paid => 'موفق',
            self::Failed => 'ناموفق',
            self::Expired => 'منقضی',
        };
    }
}
