<?php

namespace App\Modules\Identity\Exceptions;

use App\Support\Http\DomainException;

final class NotATenantMemberException extends DomainException
{
    public function __construct()
    {
        parent::__construct('شما به این کسب‌وکار دسترسی ندارید.', 'not_a_member', 403);
    }
}
