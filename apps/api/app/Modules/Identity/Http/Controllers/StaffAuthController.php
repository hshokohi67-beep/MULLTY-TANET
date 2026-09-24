<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Actions\AuthenticateStaff;
use App\Modules\Identity\Http\Requests\StaffLoginRequest;
use App\Modules\Identity\Http\Resources\StaffUserResource;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionResolver;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class StaffAuthController
{
    public function login(StaffLoginRequest $request, AuthenticateStaff $authenticate): JsonResponse
    {
        $result = $authenticate->handle(
            (string) $request->validated('identifier'),
            (string) $request->validated('password'),
            $request->validated('device_name'),
        );

        return response()->json([
            'token' => $result['token'],
            'user' => new StaffUserResource($result['user']),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * The signed-in staff user and every tenant they can open, with their permissions there.
     */
    public function me(Request $request, TenantContext $context, PermissionResolver $permissions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Cross-tenant read by design: a user's own memberships, filtered by user_id.
        $tenantIds = $context->bypass(fn () => TenantUser::query()
            ->where('user_id', $user->getKey())
            ->where('status', TenantUser::STATUS_ACTIVE)
            ->pluck('tenant_id'));

        $memberships = Tenant::query()->whereKey($tenantIds)->orderBy('name')->get()
            ->filter(fn (Tenant $tenant) => $tenant->canOperate())
            ->map(fn (Tenant $tenant) => [
                'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug],
                'permissions' => $permissions->permissions($user, $tenant),
            ])
            ->values();

        return response()->json([
            'user' => new StaffUserResource($user),
            'memberships' => $memberships,
        ]);
    }
}
