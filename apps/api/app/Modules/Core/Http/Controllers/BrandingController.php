<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\UpdateBranding;
use App\Modules\Core\Actions\UploadTenantCover;
use App\Modules\Core\Actions\UploadTenantLogo;
use App\Modules\Core\Http\Requests\UpdateBrandingRequest;
use App\Modules\Core\Http\Requests\UploadCoverRequest;
use App\Modules\Core\Http\Requests\UploadLogoRequest;
use App\Modules\Core\Http\Resources\BrandingResource;
use App\Modules\Core\Models\TenantBranding;

final class BrandingController
{
    public function show(): BrandingResource
    {
        return new BrandingResource(TenantBranding::query()->firstOrNew());
    }

    public function update(UpdateBrandingRequest $request, UpdateBranding $update): BrandingResource
    {
        return new BrandingResource($update->handle($request->validated()));
    }

    public function uploadLogo(UploadLogoRequest $request, UploadTenantLogo $upload): BrandingResource
    {
        return new BrandingResource($upload->handle($request->file('logo')));
    }

    public function uploadCover(UploadCoverRequest $request, UploadTenantCover $upload): BrandingResource
    {
        return new BrandingResource($upload->handle($request->file('cover')));
    }

    public function deleteCover(UploadTenantCover $upload): BrandingResource
    {
        return new BrandingResource($upload->remove());
    }
}
