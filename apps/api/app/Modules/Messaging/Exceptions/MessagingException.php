<?php

namespace App\Modules\Messaging\Exceptions;

use App\Support\Http\DomainException;

final class MessagingException extends DomainException
{
    public static function unknownProvider(): self
    {
        return new self('این پنل پیامکی پشتیبانی نمی‌شود.', 'sms_unknown_provider');
    }

    public static function missingField(string $label): self
    {
        return new self("«{$label}» را وارد کنید.", 'sms_missing_field');
    }

    public static function notConnected(): self
    {
        return new self('اول پنل پیامک کافه را وصل کنید.', 'sms_not_connected');
    }

    public static function campaignLocked(): self
    {
        return new self('این کمپین در حال ارسال یا ارسال‌شده است و دیگر تغییر نمی‌کند.', 'sms_campaign_locked');
    }

    public static function emptyAudience(): self
    {
        return new self('هیچ مشتری‌ای با این فیلتر اجازه‌ی پیام تبلیغاتی نداده است.', 'sms_empty_audience');
    }

    public static function scheduleInPast(): self
    {
        return new self('زمان ارسال گذشته است.', 'sms_schedule_in_past');
    }
}
