<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Models\DeliveryZone;

final readonly class DeliveryQuote
{
    public function __construct(
        public DeliveryZone $zone,
        public int $distanceMeters,
        public int $fee,              // rial, 0 when free delivery applies
        public bool $freeDeliveryApplied,
        public ?int $etaMinutes,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'zone_id' => $this->zone->id,
            'zone_name' => $this->zone->name,
            'distance_m' => $this->distanceMeters,
            'fee' => $this->fee,
            'free_delivery_applied' => $this->freeDeliveryApplied,
            'free_delivery_min' => $this->zone->free_delivery_min,
            'min_order' => $this->zone->min_order,
            'eta_minutes' => $this->etaMinutes,
        ];
    }
}
