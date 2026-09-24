<?php

namespace App\Support\Tenancy;

/**
 * A Sanctum tokenable that exists inside one tenant (customers, kitchen devices). Its tokens are
 * accepted only while that tenant is the resolved one, and only while the tokenable is active.
 */
interface TenantBoundTokenable
{
    public function tokenTenantId(): string;

    public function tokenIsActive(): bool;
}
