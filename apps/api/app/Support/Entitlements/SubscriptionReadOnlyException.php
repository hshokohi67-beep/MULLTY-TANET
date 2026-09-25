<?php

namespace App\Support\Entitlements;

use App\Support\Http\DomainException;

final class SubscriptionReadOnlyException extends DomainException
{
    public static function staff(): self
    {
        return new self('اشتراک شما به پایان رسیده و پنل فقط‌خواندنی است. برای ادامه‌ی کار، اشتراک را تمدید کنید.', 'subscription_read_only', 402);
    }

    public static function public(): self
    {
        return new self('این فروشگاه موقتاً سفارش نمی‌پذیرد.', 'store_unavailable', 402);
    }
}
