<?php

namespace App\Modules\Discounts\Support;

use App\Modules\Discounts\Models\Discount;

final readonly class AppliedDiscount
{
    public function __construct(
        public Discount $discount,
        public int $amount,          // rial actually taken off
        public int $eligibleAmount,  // rial the discount was calculated on
    ) {}

    /** @return array<string, mixed> frozen onto the order */
    public function snapshot(): array
    {
        return [
            'discount_id' => $this->discount->id,
            'name' => $this->discount->name,
            'code' => $this->discount->code,
            'kind' => $this->discount->kind->value,
            'value' => $this->discount->value,
            'amount' => $this->amount,
        ];
    }
}
