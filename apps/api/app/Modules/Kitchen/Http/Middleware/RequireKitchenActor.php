<?php

namespace App\Modules\Kitchen\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Kitchen\Models\KitchenDevice;
use App\Modules\Kitchen\Support\KitchenActor;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * KDS endpoints accept a paired device (token ability "kds") or a staff member with kds.operate.
 * Device tokens are tenant-bound (checked when Sanctum resolves them) and can't reach anything else.
 */
final class RequireKitchenActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        $actor = match (true) {
            $user instanceof KitchenDevice && $user->tokenCan('kds') => KitchenActor::device($user),
            $user instanceof User && $user->tokenCan('staff') && Gate::forUser($user)->allows(PermissionCatalog::KDS_OPERATE) => new KitchenActor('user', $user->id),
            default => throw new AuthorizationException,
        };

        if ($user instanceof KitchenDevice && ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinute()))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $request->attributes->set('kds_actor', $actor);

        return $next($request);
    }
}
