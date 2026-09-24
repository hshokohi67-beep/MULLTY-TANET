<?php

namespace App\Modules\Payments\Support;

/** Persian explanation of a gateway failure code, for staff and the customer result page. */
final class FailureMessage
{
    public static function for(?string $code): ?string
    {
        return match ($code) {
            null => null,
            '-51' => 'پرداخت انجام نشد یا توسط مشتری لغو شد.',
            '-50' => 'مبلغ پرداخت‌شده با مبلغ سفارش یکسان نیست.',
            '-54', '-55' => 'شناسه‌ی پرداخت نامعتبر است.',
            '-9' => 'اطلاعات ارسالی به درگاه نامعتبر بود.',
            '-10', '-11', '-12' => 'مرچنت کد درگاه نامعتبر یا غیرفعال است.',
            'network', 'transient', 'unreachable' => 'درگاه پرداخت در دسترس نبود.',
            default => str_starts_with($code, 'http_') ? 'درگاه پرداخت در دسترس نبود.' : 'پرداخت ناموفق بود.',
        };
    }
}
