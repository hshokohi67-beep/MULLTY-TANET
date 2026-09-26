<?php

namespace App\Modules\Storefront\Exceptions;

use App\Support\Http\DomainException;

final class LandingException extends DomainException
{
    public static function imageUnreadable(): self
    {
        return new self('این تصویر قابل پردازش نیست. یک عکس JPG، PNG یا WebP دیگر انتخاب کنید.', 'landing_image_invalid', 422);
    }

    public static function videoInvalid(): self
    {
        return new self('این ویدیو قابل استفاده نیست. یک فایل MP4 (حداکثر ۸ مگابایت) انتخاب کنید.', 'landing_video_invalid', 422);
    }

    public static function galleryFull(): self
    {
        return new self('گالری حداکثر ۱۲ عکس دارد؛ اول یکی از عکس‌های قبلی را حذف کنید.', 'landing_gallery_full', 422);
    }

    public static function unknownProducts(): self
    {
        return new self('بعضی از محصولات انتخاب‌شده دیگر در منو نیستند؛ دوباره انتخاب کنید.', 'landing_unknown_products', 422);
    }
}
