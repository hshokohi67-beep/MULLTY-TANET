<?php

namespace App\Support\Localization;

use InvalidArgumentException;

final class InvalidPhoneNumberException extends InvalidArgumentException
{
    public function __construct(string $input)
    {
        parent::__construct(sprintf('Invalid Iranian mobile number [%s].', $input));
    }
}
