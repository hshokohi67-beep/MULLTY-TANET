<?php

namespace App\Modules\Loyalty\Enums;

enum WalletTransactionType: string
{
    case OrderPayment = 'order_payment';
    case OrderRefund = 'order_refund';
    case Cashback = 'cashback';
    case CashbackReversal = 'cashback_reversal';
    case Referral = 'referral';
    case Birthday = 'birthday';
    case PointsRedeem = 'points_redeem';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::OrderPayment => 'پرداخت سفارش',
            self::OrderRefund => 'بازگشت وجه سفارش',
            self::Cashback => 'کش‌بک',
            self::CashbackReversal => 'برگشت کش‌بک',
            self::Referral => 'پاداش معرفی دوستان',
            self::Birthday => 'هدیه‌ی تولد',
            self::PointsRedeem => 'تبدیل امتیاز',
            self::Adjustment => 'اصلاح دستی',
        };
    }
}
