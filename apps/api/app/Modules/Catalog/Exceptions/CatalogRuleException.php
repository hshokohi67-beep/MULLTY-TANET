<?php

namespace App\Modules\Catalog\Exceptions;

use App\Support\Http\DomainException;
use App\Support\Localization\PersianNumber;

final class CatalogRuleException extends DomainException
{
    public static function categoryTooDeep(): self
    {
        return new self('دسته‌بندی‌ها حداکثر سه سطح می‌توانند داشته باشند.', 'category_too_deep', 422);
    }

    public static function categoryCycle(): self
    {
        return new self('یک دسته‌بندی نمی‌تواند زیرمجموعه‌ی خودش یا زیرمجموعه‌هایش باشد.', 'category_cycle', 422);
    }

    public static function categoryNotEmpty(): self
    {
        return new self('این دسته‌بندی زیرمجموعه یا محصول دارد. ابتدا آن‌ها را جابه‌جا کنید.', 'category_not_empty', 422);
    }

    public static function atLeastOneVariant(): self
    {
        return new self('هر محصول دست‌کم یک سایز/نوع فعال لازم دارد.', 'variant_required', 422);
    }

    public static function tooManyImages(int $max): self
    {
        return new self(sprintf('برای هر محصول حداکثر %s تصویر می‌توان بارگذاری کرد.', PersianNumber::toPersian((string) $max)), 'too_many_images', 422);
    }

    public static function invalidSelectionRange(): self
    {
        return new self('حداقل انتخاب نمی‌تواند از حداکثر انتخاب بیشتر باشد.', 'invalid_selection_range', 422);
    }

    public static function tooManyDefaults(): self
    {
        return new self('تعداد گزینه‌های پیش‌فرض از حداکثر انتخاب مجاز بیشتر است.', 'too_many_defaults', 422);
    }
}
