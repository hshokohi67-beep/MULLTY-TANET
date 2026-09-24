<?php

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Models\CustomerAddress;
use App\Support\Localization\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerAddress */
final class CustomerAddressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone_e164 ? PhoneNormalizer::toLocal($this->recipient_phone_e164) : null,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'building_number' => $this->building_number,
            'floor' => $this->floor,
            'unit' => $this->unit,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->notes,
            'is_default' => $this->is_default,
            'has_location' => $this->hasCoordinates(),
        ];
    }
}
