<?php

namespace App\Modules\Discounts\Exceptions;

use App\Support\Http\DomainException;

final class CouponException extends DomainException
{
    public static function notFound(): self
    {
        return new self('کد تخفیف معتبر نیست.', 'coupon_invalid', 422);
    }

    public static function notActiveNow(): self
    {
        return new self('این کد تخفیف در حال حاضر فعال نیست.', 'coupon_inactive', 422);
    }

    public static function exhausted(): self
    {
        return new self('ظرفیت استفاده از این کد تخفیف تمام شده است.', 'coupon_exhausted', 422);
    }

    public static function alreadyUsed(): self
    {
        return new self('شما قبلاً از این کد تخفیف استفاده کرده‌اید.', 'coupon_already_used', 422);
    }

    public static function loginRequired(): self
    {
        return new self('برای استفاده از این کد تخفیف ابتدا وارد شوید.', 'coupon_login_required', 422);
    }

    public static function minimumOrder(string $amount): self
    {
        return new self(sprintf('این کد تخفیف برای سفارش‌های بالای %s است.', $amount), 'coupon_min_order', 422);
    }

    public static function notApplicable(): self
    {
        return new self('این کد تخفیف شامل آیتم‌های سبد شما یا این نوع سفارش نمی‌شود.', 'coupon_not_applicable', 422);
    }
}
