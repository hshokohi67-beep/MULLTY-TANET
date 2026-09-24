<?php

namespace App\Modules\Customers\Exceptions;

use App\Support\Http\DomainException;

final class AddressLimitException extends DomainException
{
    public function __construct()
    {
        parent::__construct('حداکثر ۱۰ آدرس می‌توانید ذخیره کنید. یکی از آدرس‌های قبلی را حذف کنید.', 'address_limit', 422);
    }
}
