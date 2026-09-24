<?php

namespace App\Modules\Catalog\Data;

final readonly class VariantData
{
    /**
     * @param  ?string  $id  existing variant to update; null creates a new one
     * @param  int  $basePrice  rial
     */
    public function __construct(
        public ?string $id,
        public ?string $name,
        public int $basePrice,
        public ?string $sku = null,
        public bool $isActive = true,
    ) {}
}
