<?php

namespace App\Support\Tenancy;

use App\Modules\Core\Models\Tenant;
use Closure;

/**
 * The single source of truth for "which tenant is this request/job acting for".
 *
 * Tenant-owned models refuse to query without a tenant here (fail-closed). Only
 * platform-level code may run without one, and must say so explicitly through
 * {@see self::bypass()}.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private int $bypassDepth = 0;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * @throws MissingTenantContextException
     */
    public function require(): Tenant
    {
        return $this->tenant ?? throw new MissingTenantContextException;
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    /**
     * Run a callback as a specific tenant, restoring the previous context afterwards.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }

    /**
     * Run platform-level code that intentionally reads across tenants.
     * Keep these call sites rare and reviewed.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function bypass(Closure $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }
}
