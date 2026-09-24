<?php

namespace App\Support\Tenancy;

use LogicException;

/**
 * Thrown when tenant-owned data is touched without a tenant context.
 * This is always a programming error, never a user error.
 */
final class MissingTenantContextException extends LogicException
{
    public function __construct(string $message = 'Tenant-owned data was accessed without a tenant context.')
    {
        parent::__construct($message);
    }
}
