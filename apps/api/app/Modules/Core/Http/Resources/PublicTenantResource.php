<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public projection of a tenant (storefront, SEO, PWA manifest).
 * Explicit allow-list: never add internal fields here.
 *
 * @mixin Tenant
 */
final class PublicTenantResource extends JsonResource
{
    public function __construct(Tenant $tenant, private readonly ?TenantBranding $branding)
    {
        parent::__construct($tenant);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'display_currency_unit' => $this->display_currency_unit->value,
            'branding' => $this->branding ? new BrandingResource($this->branding) : null,
        ];
    }
}
