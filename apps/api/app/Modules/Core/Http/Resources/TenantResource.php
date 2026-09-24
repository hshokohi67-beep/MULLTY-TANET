<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
final class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'currency' => $this->currency,
            'display_currency_unit' => $this->display_currency_unit->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
