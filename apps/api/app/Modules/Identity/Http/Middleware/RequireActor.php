<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated token belongs to the expected kind of actor:
 *   actor:staff     → User with a "staff" token
 *   actor:customer  → Customer with a "customer" token
 *   actor:platform  → User with a "staff" token and the platform-admin flag
 */
final class RequireActor
{
    public function handle(Request $request, Closure $next, string $kind): Response
    {
        // Sanctum resolves any tokenable: the actor may be a User or a Customer.
        $actor = $request->user('sanctum');

        $allowed = match ($kind) {
            'staff' => $actor instanceof User && $actor->tokenCan('staff'),
            'platform' => $actor instanceof User && $actor->tokenCan('staff') && $actor->is_platform_admin,
            'customer' => $actor instanceof Customer && $actor->tokenCan('customer'),
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
