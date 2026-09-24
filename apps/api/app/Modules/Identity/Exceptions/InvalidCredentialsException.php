<?php

namespace App\Modules\Identity\Exceptions;

use App\Support\Http\DomainException;

final class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('نام کاربری یا رمز عبور صحیح نیست.', 'invalid_credentials', 422);
    }
}
