<?php

namespace App\Modules\Identity\Exceptions;

use App\Support\Http\DomainException;
use App\Support\Localization\PersianNumber;

final class OtpException extends DomainException
{
    public static function cooldown(int $seconds): self
    {
        return new self(sprintf('برای درخواست کد جدید %s ثانیه صبر کنید.', PersianNumber::toPersian((string) $seconds)), 'otp_cooldown', 429);
    }

    public static function dailyLimit(): self
    {
        return new self('تعداد درخواست کد برای این شماره امروز به حد مجاز رسیده است. فردا دوباره تلاش کنید.', 'otp_daily_limit', 429);
    }

    public static function invalid(): self
    {
        return new self('کد واردشده صحیح نیست.', 'otp_invalid', 422);
    }

    public static function expired(): self
    {
        return new self('کد تایید منقضی شده است. لطفاً کد جدید دریافت کنید.', 'otp_expired', 422);
    }

    public static function tooManyAttempts(): self
    {
        return new self('تعداد تلاش‌های ناموفق زیاد بود. لطفاً کد جدید دریافت کنید.', 'otp_locked', 429);
    }

    public static function deliveryFailed(): self
    {
        return new self('ارسال پیامک با خطا مواجه شد. چند لحظه بعد دوباره تلاش کنید.', 'otp_delivery_failed', 503);
    }
}
