<?php

namespace App\Modules\Advertising\Exceptions;

use App\Support\Http\DomainException;

final class AdException extends DomainException
{
    public static function notEditable(): self
    {
        return new self('این کمپین دیگر قابل ویرایش نیست.', 'ad_not_editable');
    }

    public static function wrongState(): self
    {
        return new self('این کار در وضعیت فعلی کمپین ممکن نیست.', 'ad_wrong_state');
    }

    public static function placementUnavailable(): self
    {
        return new self('این جایگاه تبلیغ فعلاً در دسترس نیست.', 'ad_placement_unavailable');
    }

    public static function imageRequired(): self
    {
        return new self('برای بنر، یک تصویر افقی بارگذاری کنید.', 'ad_image_required');
    }

    public static function startPassed(): self
    {
        return new self('روز شروع گذشته است؛ تاریخ کمپین را به‌روز کنید.', 'ad_start_passed');
    }

    public static function full(): self
    {
        return new self('این جایگاه در این بازه پر است؛ روز دیگری را انتخاب کنید.', 'ad_placement_full');
    }

    public static function notListed(): self
    {
        return new self('کافه‌ی شما هنوز در کافه‌گردی نمایش داده نمی‌شود؛ اول از بخش «بازارگاه» معرفی‌اش را کامل کنید.', 'ad_store_not_listed');
    }

    public static function unknownCity(): self
    {
        return new self('فقط شهرهایی را می‌توانید انتخاب کنید که در آن شعبه دارید.', 'ad_unknown_city');
    }

    public static function paymentInProgress(): self
    {
        return new self('پرداخت این کمپین در جریان است؛ چند دقیقه‌ی دیگر دوباره تلاش کنید.', 'ad_payment_in_progress');
    }
}
