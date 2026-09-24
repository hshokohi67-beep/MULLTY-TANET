<?php

namespace App\Modules\Catalog\Exceptions;

use App\Support\Http\DomainException;

final class InvalidPriceException extends DomainException
{
    public static function negative(): self
    {
        return new self('با این تغییر، قیمت بعضی از آیتم‌ها منفی می‌شود. مقدار تغییر را کمتر کنید.', 'price_negative', 422);
    }

    public static function baseRequired(): self
    {
        return new self('قیمت پایه‌ی هر سایز الزامی است.', 'base_price_required', 422);
    }
}
