<?php

namespace App\Modules\Core\Exceptions;

use App\Support\Http\DomainException;

final class SignupException extends DomainException
{
    public static function closed(): self
    {
        return new self('ثبت‌نام آنلاین فعلاً بسته است؛ با پشتیبانی کافه‌یار تماس بگیرید.', 'signup_closed', 403);
    }

    public static function phoneTaken(): self
    {
        return new self('با این شماره قبلاً حساب ساخته شده است؛ از «ورود» وارد شوید.', 'signup_phone_taken', 422);
    }

    public static function slugTaken(): self
    {
        return new self('این آدرس گرفته شده است؛ آدرس دیگری انتخاب کنید.', 'signup_slug_taken', 422);
    }
}
