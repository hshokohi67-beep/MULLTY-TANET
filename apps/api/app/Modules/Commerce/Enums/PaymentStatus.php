<?php

namespace App\Modules\Commerce\Enums;

/** Separate from order status. Managed by the Payments module (Phase 4). */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'پرداخت‌نشده',
            self::Pending => 'در حال پرداخت',
            self::PartiallyPaid => 'پرداخت ناقص',
            self::Paid => 'پرداخت‌شده',
            self::Refunded => 'بازگشت وجه',
            self::PartiallyRefunded => 'بازگشت بخشی از وجه',
        };
    }
}
