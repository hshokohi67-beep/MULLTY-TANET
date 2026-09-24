<?php

namespace App\Modules\Core\Http\Controllers\Platform;

use App\Modules\Core\Actions\CreateTenant;
use App\Modules\Core\Http\Requests\CreateTenantRequest;
use App\Modules\Core\Http\Resources\TenantResource;
use App\Modules\Core\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Super-admin endpoints. Tenants are not tenant-scoped, so no bypass is needed here.
 */
final class PlatformTenantController
{
    public function index(): AnonymousResourceCollection
    {
        return TenantResource::collection(Tenant::query()->latest()->paginate(30));
    }

    public function store(CreateTenantRequest $request, CreateTenant $create): JsonResponse
    {
        return (new TenantResource($create->handle($request->toData())))->response()->setStatusCode(201);
    }
}
