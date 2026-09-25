<?php

namespace App\Modules\Operations\Exceptions;

use App\Support\Http\DomainException;

final class OperationsException extends DomainException
{
    public static function shiftOverlap(string $name): self
    {
        return new self("برای «{$name}» در این بازه شیفت دیگری ثبت شده است.", 'shift_overlap', 422);
    }

    public static function shiftTooLong(): self
    {
        return new self('شیفت باید بعد از شروع تمام شود و حداکثر ۱۶ ساعت باشد.', 'shift_invalid', 422);
    }

    public static function alreadyClockedIn(): self
    {
        return new self('ورود شما قبلاً ثبت شده است؛ اول «خروج» را بزنید.', 'already_clocked_in', 422);
    }

    public static function notClockedIn(): self
    {
        return new self('ورودی برای شما ثبت نشده است.', 'not_clocked_in', 422);
    }

    public static function notAnEmployee(): self
    {
        return new self('حساب شما به هیچ کارمندی وصل نیست؛ از مدیر بخواهید در «کارکنان» شما را وصل کند.', 'not_an_employee', 403);
    }

    public static function userNotMember(): self
    {
        return new self('این کاربر عضو تیم این کافه نیست.', 'user_not_member', 422);
    }

    public static function categoryInUse(): self
    {
        return new self('برای این دسته هزینه ثبت شده؛ به‌جای حذف، غیرفعالش کنید.', 'category_in_use', 422);
    }
}
