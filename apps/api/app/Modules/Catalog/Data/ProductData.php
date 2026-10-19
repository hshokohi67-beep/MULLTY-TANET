<?php

namespace App\Modules\Catalog\Data;

final readonly class ProductData
{
    /**
     * @param  list<string>  $categoryIds
     * @param  array<string, int>|null  $nutrition
     * @param  list<string>|null  $dietaryTags
     * @param  list<int>|null  $specialDays
     * @param  list<string>|null  $allergens
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
        public ?array $specialDays = null,   // null = leave as is, [] = none
        public ?string $nameEn = null,
        public ?string $ingredients = null,
        public ?array $allergens = null,
        public ?string $caffeineSource = null,
        public ?string $caffeineDose = null,
        public bool $isNew = false,
    ) {}
}
