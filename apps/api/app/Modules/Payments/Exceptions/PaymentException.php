<?php

namespace App\Modules\Payments\Exceptions;

use App\Support\Http\DomainException;

final class PaymentException extends DomainException
{
    public static function onlineUnavailable(): self
    {
        return new self('پرداخت اینترنتی برای این کافه فعال نیست.', 'payment_online_unavailable', 422);
    }

    public static function notAwaitingPayment(): self
    {
        return new self('این سفارش منتظر پرداخت اینترنتی نیست.', 'payment_not_awaiting', 422);
    }

    public static function inProgress(): self
    {
        return new self('در حال اتصال به درگاه پرداخت هستیم؛ چند لحظه بعد دوباره تلاش کنید.', 'payment_in_progress', 409);
    }

    public static function gatewayRejected(): self
    {
        return new self('درگاه پرداخت در حال حاضر پاسخ نمی‌دهد. لطفاً کمی بعد دوباره تلاش کنید یا در صندوق پرداخت کنید.', 'payment_gateway_error', 502);
    }

    public static function notFound(): self
    {
        return new self('پرداخت پیدا نشد.', 'payment_not_found', 404);
    }

    public static function orderClosed(): self
    {
        return new self('برای سفارش لغوشده یا ردشده نمی‌توان پرداخت ثبت کرد.', 'payment_order_closed', 422);
    }

    public static function nothingDue(): self
    {
        return new self('مبلغی برای پرداخت باقی نمانده است.', 'payment_nothing_due', 422);
    }

    public static function exceedsRemaining(string $remaining): self
    {
        return new self(sprintf('مبلغ پرداخت از باقی‌مانده‌ی سفارش (%s) بیشتر است.', $remaining), 'payment_exceeds_remaining', 422);
    }

    public static function notRefundable(): self
    {
        return new self('فقط پرداخت‌های موفق را می‌توان بازگرداند.', 'refund_not_allowed', 422);
    }

    public static function refundExceeds(string $refundable): self
    {
        return new self(sprintf('مبلغ بازگشت از مبلغ قابل بازگشت (%s) بیشتر است.', $refundable), 'refund_exceeds', 422);
    }

    public static function walletRefundMethod(): self
    {
        return new self('پرداخت با کیف پول فقط به کیف پول برگردانده می‌شود و سایر پرداخت‌ها به کیف پول برنمی‌گردند.', 'refund_wallet_method', 422);
    }

    public static function idempotencyConflict(): self
    {
        return new self('این شناسه‌ی درخواست قبلاً برای عملیات دیگری استفاده شده است.', 'idempotency_conflict', 409);
    }
}
