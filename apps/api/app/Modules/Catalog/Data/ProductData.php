<?php

namespace App\Modules\Catalog\Data;

final readonly class ProductData
{
    /**
     * @param  list<string>  $categoryIds
     * @param  array<string, int>|null  $nutrition
     * @param  list<string>|null  $dietaryTags
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public bool $isActive = true,
        public bool $isFeatured = false,
        public int $sort = 0,
        public array $categoryIds = [],
        public ?array $nutrition = null,
        public ?array $dietaryTags = null,
        public ?string $temperature = null,
    ) {}
}
