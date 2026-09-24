<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Http\Resources\PublicTenantResource;
use App\Modules\Core\Models\TenantBranding;
use App\Support\Tenancy\TenantContext;

final class PublicTenantController
{
    public function show(TenantContext $context): PublicTenantResource
    {
        return new PublicTenantResource($context->require(), TenantBranding::query()->first());
    }
}
