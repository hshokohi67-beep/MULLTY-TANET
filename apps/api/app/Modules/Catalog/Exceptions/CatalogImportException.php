<?php

namespace App\Modules\Catalog\Exceptions;

use App\Support\Http\DomainException;

final class CatalogImportException extends DomainException
{
    public static function unreadable(): self
    {
        return new self('این فایل خوانده نشد. فایل اکسل (xlsx) یا CSV بفرستید؛ فایل‌های قدیمی xls را اول در اکسل با «Save As» به xlsx تبدیل کنید.', 'catalog_import_unreadable', 422);
    }

    public static function empty(): self
    {
        return new self('فایل ردیفی ندارد. ردیف اول عنوان ستون‌هاست و محصولات از ردیف دوم شروع می‌شوند.', 'catalog_import_empty', 422);
    }

    public static function missingColumns(): self
    {
        return new self('ستون «نام» و «قیمت» را مشخص کنید.', 'catalog_import_columns', 422);
    }
}
