<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Commerce\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryZone */
final class DeliveryZoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'type' => $this->type,
            'radius_m' => $this->radius_m,
            'delivery_fee' => $this->delivery_fee,
            'free_delivery_min' => $this->free_delivery_min,
            'min_order' => $this->min_order,
            'eta_minutes' => $this->eta_minutes,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
        ];
    }
}
