<?php

namespace App\Modules\Notifications\Exceptions;

use App\Support\Http\DomainException;

final class PushException extends DomainException
{
    public static function disabled(): self
    {
        return new self('اعلان مرورگر فعلاً روی این سامانه فعال نیست.', 'push_disabled', 422);
    }

    public static function unsupported(): self
    {
        return new self('این مرورگر از اعلان پشتیبانی نمی‌کند؛ مرورگر دیگری را امتحان کنید.', 'push_unsupported', 422);
    }
}
