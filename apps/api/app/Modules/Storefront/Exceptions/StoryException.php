<?php

namespace App\Modules\Storefront\Exceptions;

use App\Support\Http\DomainException;

final class StoryException extends DomainException
{
    public static function imageUnreadable(): self
    {
        return new self('این تصویر قابل پردازش نیست. یک عکس JPG، PNG یا WebP دیگر انتخاب کنید.', 'story_image_invalid', 422);
    }

    public static function tooMany(): self
    {
        return new self('حداکثر ۳۰ استوری در صف نمایش می‌توانید داشته باشید؛ چندتا از قبلی‌ها را حذف کنید.', 'story_limit', 422);
    }
}
