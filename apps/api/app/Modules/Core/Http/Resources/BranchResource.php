<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Support\Localization\Weekday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
final class BranchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'phone' => $this->phone,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
            'opening_hours' => $this->whenLoaded('openingHours', fn () => $this->openingHours->map(fn (BranchOpeningHour $h) => [
                'weekday' => $h->weekday,
                'weekday_label' => Weekday::from($h->weekday)->label(),
                'opens_at' => substr($h->opens_at, 0, 5),
                'closes_at' => substr($h->closes_at, 0, 5),
                'overnight' => substr($h->closes_at, 0, 5) <= substr($h->opens_at, 0, 5),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
