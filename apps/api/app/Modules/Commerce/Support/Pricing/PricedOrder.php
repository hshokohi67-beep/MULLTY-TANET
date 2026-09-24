<?php

namespace App\Modules\Commerce\Support\Pricing;

use App\Modules\Commerce\Support\DeliveryQuote;
use App\Modules\Discounts\Support\AppliedDiscount;
use Carbon\CarbonImmutable;

/**
 * The result of pricing: what the customer sees in the cart and what checkout freezes into the order.
 * Invalid lines (sold out, broken modifiers) are listed but excluded from the totals.
 */
final readonly class PricedOrder
{
    /**
     * @param  list<PricedLine>  $lines
     * @param  list<array{code: string, message: string}>  $issues  order-level problems (closed branch, delivery…)
     */
    public function __construct(
        public array $lines,
        public int $subtotal,
        public ?AppliedDiscount $discount,
        public ?DeliveryQuote $delivery,
        public int $total,
        public ?CarbonImmutable $scheduledFor,
        public array $issues = [],
    ) {}

    public function discountTotal(): int
    {
        return $this->discount !== null ? $this->discount->amount : 0;
    }

    public function deliveryFee(): int
    {
        return $this->delivery !== null ? $this->delivery->fee : 0;
    }

    /** @return list<PricedLine> */
    public function validLines(): array
    {
        return array_values(array_filter($this->lines, fn (PricedLine $l) => $l->isValid()));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (PricedLine $l) => $l->toArray(), $this->lines),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount ? [
                'name' => $this->discount->discount->name,
                'code' => $this->discount->discount->code,
                'amount' => $this->discount->amount,
            ] : null,
            'delivery' => $this->delivery?->toArray(),
            'delivery_fee' => $this->deliveryFee(),
            'total' => $this->total,
            'scheduled_for' => $this->scheduledFor?->toIso8601String(),
            'issues' => $this->issues,
            'can_checkout' => $this->issues === [] && $this->validLines() !== [] && count($this->validLines()) === count($this->lines),
        ];
    }
}
