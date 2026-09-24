<?php

namespace App\Modules\Core\Data;

final readonly class BranchData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $phone = null,
        public ?string $province = null,
        public ?string $city = null,
        public ?string $address = null,
        public ?string $postalCode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public bool $isActive = true,
    ) {}

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'phone' => $this->phone,
            'province' => $this->province,
            'city' => $this->city,
            'address' => $this->address,
            'postal_code' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->isActive,
        ];
    }
}
