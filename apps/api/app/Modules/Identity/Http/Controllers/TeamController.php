<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\AddTeamMember;
use App\Modules\Identity\Actions\UpdateMemberRoles;
use App\Modules\Identity\Http\Requests\AddTeamMemberRequest;
use App\Modules\Identity\Http\Requests\UpdateMemberRolesRequest;
use App\Modules\Identity\Http\Resources\RoleResource;
use App\Modules\Identity\Http\Resources\TeamMemberResource;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TeamController
{
    public function index(): AnonymousResourceCollection
    {
        return TeamMemberResource::collection(
            TenantUser::query()->with(['user', 'roles'])->orderBy('created_at')->get(),
        );
    }

    public function store(AddTeamMemberRequest $request, AddTeamMember $add): JsonResponse
    {
        $member = $add->handle(
            $request->user(),
            (string) $request->validated('name'),
            $request->phoneE164(),
            $request->validated('password'),
            $request->validated('role_ids'),
        );

        return (new TeamMemberResource($member))->response()->setStatusCode(201);
    }

    public function updateRoles(UpdateMemberRolesRequest $request, TenantUser $member, UpdateMemberRoles $update): TeamMemberResource
    {
        return new TeamMemberResource($update->handle($request->user(), $member, $request->validated('role_ids'))->load('user'));
    }

    public function roles(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::query()->with('permissions')->orderBy('created_at')->get());
    }

    public function permissions(): JsonResponse
    {
        $items = collect(PermissionCatalog::all())
            ->map(fn (array $definition, string $key) => ['key' => $key, ...$definition])
            ->values();

        return response()->json(['data' => $items]);
    }
}
