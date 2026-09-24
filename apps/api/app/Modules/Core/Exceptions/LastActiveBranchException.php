<?php

namespace App\Modules\Core\Exceptions;

use App\Support\Http\DomainException;

final class LastActiveBranchException extends DomainException
{
    public function __construct()
    {
        parent::__construct('حداقل یک شعبه فعال لازم است. ابتدا شعبه دیگری را فعال کنید.', 'last_active_branch', 422);
    }
}
