<?php

namespace App\Modules\Inventory\Exceptions;

use App\Support\Http\DomainException;

final class InventoryException extends DomainException
{
    public static function unitMismatch(string $unit): self
    {
        return new self("واحد «{$unit}» برای این ماده قابل استفاده نیست.", 'unit_mismatch', 422);
    }

    public static function ingredientInUse(): self
    {
        return new self('این ماده در دستور پخت یا سفارش خرید استفاده شده است؛ به‌جای حذف، غیرفعالش کنید.', 'ingredient_in_use', 422);
    }

    public static function purchaseNotEditable(): self
    {
        return new self('فقط سفارش خرید پیش‌نویس قابل ویرایش است.', 'purchase_not_editable', 422);
    }

    public static function purchaseClosed(): self
    {
        return new self('این سفارش خرید بسته یا لغو شده است.', 'purchase_closed', 422);
    }

    public static function overpayment(string $due): self
    {
        return new self("مبلغ پرداخت از مانده‌ی بدهی ({$due}) بیشتر است.", 'overpayment', 422);
    }

    public static function purchaseHasReceipts(): self
    {
        return new self('بخشی از این سفارش تحویل گرفته شده و قابل لغو نیست.', 'purchase_has_receipts', 422);
    }
}
