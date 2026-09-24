<?php

namespace App\Support\Tenancy;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the X-Tenant (slug) or X-Tenant-Domain header and
 * installs it in the TenantContext for the duration of the request.
 * Must run before authentication (registered in the middleware priority list).
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request) ?? throw new TenantNotResolvedException;

        if (! $tenant->canOperate()) {
            throw new TenantSuspendedException;
        }

        $this->context->set($tenant);
        app()->setLocale($tenant->locale);

        try {
            return $next($request);
        } finally {
            $this->context->forget();
        }
    }

    private function resolve(Request $request): ?Tenant
    {
        $slug = $request->header((string) config('tenancy.header'));

        if (is_string($slug) && $slug !== '') {
            return Tenant::query()->where('slug', mb_strtolower($slug))->first();
        }

        $domain = $request->header((string) config('tenancy.domain_header'));

        if (is_string($domain) && $domain !== '') {
            $host = mb_strtolower(preg_replace('/:\d+$/', '', trim($domain)) ?? '');
            $host = preg_replace('/^www\./', '', $host) ?? $host;

            $tenantId = $this->context->bypass(
                fn () => TenantDomain::query()->where('domain', $host)->value('tenant_id'),
            );

            return $tenantId ? Tenant::query()->find($tenantId) : null;
        }

        return null;
    }
}
