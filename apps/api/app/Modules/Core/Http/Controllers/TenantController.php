<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\UpdateTenantProfile;
use App\Modules\Core\Http\Requests\UpdateTenantRequest;
use App\Modules\Core\Http\Resources\TenantResource;
use App\Support\Tenancy\TenantContext;

final class TenantController
{
    public function show(TenantContext $context): TenantResource
    {
        return new TenantResource($context->require());
    }

    public function update(UpdateTenantRequest $request, TenantContext $context, UpdateTenantProfile $update): TenantResource
    {
        return new TenantResource($update->handle($context->require(), $request->validated()));
    }
}
