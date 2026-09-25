<?php

namespace App\Modules\Billing\Exceptions;

use App\Support\Http\DomainException;

final class BillingException extends DomainException
{
    public static function planUnavailable(): self
    {
        return new self('این پلن در دسترس نیست.', 'plan_unavailable');
    }

    public static function addonUnavailable(string $name): self
    {
        return new self("افزونه‌ی «{$name}» برای این پلن قابل انتخاب نیست.", 'addon_unavailable');
    }

    public static function invoiceNotPayable(): self
    {
        return new self('این صورت‌حساب قابل پرداخت نیست.', 'invoice_not_payable');
    }

    public static function gatewayFailed(): self
    {
        return new self('اتصال به درگاه پرداخت برقرار نشد؛ چند دقیقه‌ی دیگر دوباره تلاش کنید.', 'gateway_unavailable', 503);
    }

    public static function paymentFailed(): self
    {
        return new self('پرداخت انجام نشد. اگر مبلغی از حساب شما کم شده، طی ۷۲ ساعت برمی‌گردد.', 'payment_failed');
    }

    public static function notCancellable(): self
    {
        return new self('فقط اشتراک پرداخت‌شده و فعال قابل لغو است.', 'subscription_not_cancellable');
    }

    public static function notResumable(): self
    {
        return new self('این اشتراک لغو نشده یا دوره‌اش تمام شده است؛ برای ادامه تمدید کنید.', 'subscription_not_resumable');
    }

    public static function unknownFeature(): self
    {
        return new self('این امکان تعریف نشده است.', 'unknown_feature');
    }
}
