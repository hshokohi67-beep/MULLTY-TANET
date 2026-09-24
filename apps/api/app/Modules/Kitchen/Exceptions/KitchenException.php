<?php

namespace App\Modules\Kitchen\Exceptions;

use App\Support\Http\DomainException;

final class KitchenException extends DomainException
{
    public static function invalidStep(string $from, string $to): self
    {
        return new self(sprintf('این آیتم از «%s» به «%s» نمی‌رود.', $from, $to), 'kitchen_invalid_step', 422);
    }

    public static function orderClosed(): self
    {
        return new self('این سفارش بسته شده است و در آشپزخانه قابل تغییر نیست.', 'kitchen_order_closed', 422);
    }

    public static function pairingCodeInvalid(): self
    {
        return new self('کد اتصال معتبر نیست یا منقضی شده است.', 'kitchen_pairing_invalid', 422);
    }

    public static function stationInUse(): self
    {
        return new self('این ایستگاه سفارش یا دستگاه فعال دارد؛ به‌جای حذف، غیرفعالش کنید.', 'kitchen_station_in_use', 422);
    }

    public static function stationBranchMismatch(): self
    {
        return new self('ایستگاه انتخاب‌شده متعلق به این شعبه نیست.', 'kitchen_station_branch', 422);
    }
}
