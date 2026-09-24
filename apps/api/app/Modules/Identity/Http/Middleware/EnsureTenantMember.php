<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Exceptions\NotATenantMemberException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionResolver;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff may only act inside tenants where they hold an active membership.
 * Runs after ResolveTenant and auth.
 */
final class EnsureTenantMember
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionResolver $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $membership = $user instanceof User ? $this->permissions->membership($user, $this->context->require()) : null;

        if (! $membership?->isActive()) {
            throw new NotATenantMemberException;
        }

        return $next($request);
    }
}
