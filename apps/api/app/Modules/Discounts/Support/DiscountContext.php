<?php

namespace App\Modules\Discounts\Support;

use Carbon\CarbonImmutable;

/**
 * Everything the engine needs to know about an order being priced. Amounts are integer rial.
 */
final readonly class DiscountContext
{
    /**
     * @param  list<array{product_id: string, category_ids: list<string>, line_total: int}>  $lines
     */
    public function __construct(
        public string $branchId,
        public string $orderType,
        public ?string $customerId,
        public array $lines,
        public int $subtotal,
        public CarbonImmutable $now,       // in the tenant's timezone
        public ?string $couponCode = null,
        public ?string $customerTierId = null,
    ) {}
}
