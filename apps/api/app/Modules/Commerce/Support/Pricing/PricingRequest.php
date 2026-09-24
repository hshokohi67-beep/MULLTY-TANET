<?php

namespace App\Modules\Commerce\Support\Pricing;

use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Core\Models\Branch;
use App\Modules\Customers\Models\CustomerAddress;
use Carbon\CarbonImmutable;

final readonly class PricingRequest
{
    /**
     * @param  list<array{product_id: string, variant_id: string, quantity: int, modifier_ids?: ?list<string>, note?: ?string, ref?: ?string}>  $lines
     */
    public function __construct(
        public Branch $branch,
        public OrderType $orderType,
        public array $lines,
        public ?string $customerId = null,
        public ?string $couponCode = null,
        public ?CustomerAddress $address = null,
        public ?CarbonImmutable $scheduledFor = null,
    ) {}
}
