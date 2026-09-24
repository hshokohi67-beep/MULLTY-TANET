<?php

namespace App\Modules\Commerce\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';
    case QrTable = 'qr_table';
    case Counter = 'counter';
    case Online = 'online';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'سالن',
            self::Takeaway => 'بیرون‌بر',
            self::Delivery => 'ارسال با پیک',
            self::QrTable => 'سفارش از میز',
            self::Counter => 'پیشخوان',
            self::Online => 'آنلاین',
            self::Phone => 'تلفنی',
        };
    }

    /**
     * Types a customer can place themselves from the storefront.
     *
     * @return list<self>
     */
    public static function customerFacing(): array
    {
        return [self::Takeaway, self::Delivery, self::QrTable];
    }

    /**
     * Types staff can register from the dashboard. Delivery by phone needs an address form
     * for the customer and arrives with the dashboard order screen (extension point).
     *
     * @return list<self>
     */
    public static function staffFacing(): array
    {
        return [self::DineIn, self::Takeaway, self::Counter, self::Phone];
    }

    public function needsAddress(): bool
    {
        return $this === self::Delivery;
    }

    public function needsTable(): bool
    {
        return $this === self::QrTable;
    }
}
