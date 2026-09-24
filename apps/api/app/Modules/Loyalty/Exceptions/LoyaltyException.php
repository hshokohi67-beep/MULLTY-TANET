<?php

namespace App\Modules\Loyalty\Exceptions;

use App\Support\Http\DomainException;

final class LoyaltyException extends DomainException
{
    public static function insufficientWallet(string $balance): self
    {
        return new self(sprintf('موجودی کیف پول کافی نیست (موجودی: %s).', $balance), 'wallet_insufficient', 422);
    }

    public static function walletEmpty(): self
    {
        return new self('کیف پول شما موجودی ندارد.', 'wallet_empty', 422);
    }

    public static function walletPaymentsDisabled(): self
    {
        return new self('پرداخت با کیف پول در این کافه فعال نیست.', 'wallet_payments_disabled', 422);
    }

    public static function orderHasNoCustomer(): self
    {
        return new self('این سفارش به مشتری عضو باشگاه وصل نیست.', 'order_without_customer', 422);
    }

    public static function insufficientPoints(): self
    {
        return new self('امتیاز شما کافی نیست.', 'points_insufficient', 422);
    }

    public static function belowMinimumRedeem(string $minimum): self
    {
        return new self(sprintf('حداقل %s امتیاز برای تبدیل لازم است.', $minimum), 'points_below_minimum', 422);
    }

    public static function programDisabled(): self
    {
        return new self('باشگاه مشتریان این کافه فعال نیست.', 'loyalty_disabled', 422);
    }

    public static function referralInvalid(): self
    {
        return new self('کد معرف معتبر نیست.', 'referral_invalid', 422);
    }

    public static function referralNotAllowed(): self
    {
        return new self('کد معرف فقط تا ۷ روز پس از عضویت و پیش از اولین خرید قابل ثبت است.', 'referral_not_allowed', 422);
    }

    public static function referralAlreadySet(): self
    {
        return new self('کد معرف قبلاً ثبت شده است.', 'referral_already_set', 422);
    }

    public static function tierInUse(): self
    {
        return new self('این سطح به مشتریانی اختصاص دارد و قابل حذف نیست.', 'tier_in_use', 422);
    }

    public static function idempotencyConflict(): self
    {
        return new self('این شناسه‌ی درخواست قبلاً برای عملیات دیگری استفاده شده است.', 'idempotency_conflict', 409);
    }
}
