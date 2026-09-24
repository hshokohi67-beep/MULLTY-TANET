<?php

namespace App\Modules\Customers\Exceptions;

use App\Support\Http\DomainException;

final class CustomerException extends DomainException
{
    public static function birthdayLocked(): self
    {
        return new self('تاریخ تولد قبلاً ثبت شده است. برای تغییر آن با کافه تماس بگیرید.', 'birthday_locked', 422);
    }

    public static function invalidBirthday(): self
    {
        return new self('تاریخ تولد معتبر نیست.', 'birthday_invalid', 422);
    }
}
