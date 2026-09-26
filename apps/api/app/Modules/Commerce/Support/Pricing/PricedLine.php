<?php

namespace App\Modules\Commerce\Support\Pricing;

use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Discounts\Support\AppliedDiscount;

final class PricedLine
{
    /**
     * @param  list<array{modifier_id: string, group_name: string, name: string, price_delta: int}>  $modifiers
     * @param  list<string>  $categoryIds
     */
    public function __construct(
        public readonly ?string $ref,
        public readonly string $productId,
        public readonly string $variantId,
        public readonly string $productName,
        public readonly ?string $variantName,
        public readonly int $unitPrice,
        public readonly array $modifiers,
        public readonly int $modifiersTotal,
        public readonly int $quantity,
        public readonly int $lineTotal,
        public readonly ?string $note,
        public readonly array $categoryIds,
        public readonly ?CommerceException $problem = null,
        public readonly ?AppliedDiscount $offer = null,   // the menu offer on this line («نمایش روی منو»)
    ) {}

    public function withOffer(AppliedDiscount $offer): self
    {
        return new self(
            $this->ref, $this->productId, $this->variantId, $this->productName, $this->variantName, $this->unitPrice,
            $this->modifiers, $this->modifiersTotal, $this->quantity, $this->lineTotal, $this->note, $this->categoryIds, $this->problem, $offer,
        );
    }

    /** What the line costs after its menu offer. */
    public function payable(): int
    {
        return $this->lineTotal - ($this->offer->amount ?? 0);
    }

    public function isValid(): bool
    {
        return $this->problem === null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ref' => $this->ref,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'product_name' => $this->productName,
            'variant_name' => $this->variantName,
            'unit_price' => $this->unitPrice,
            'modifiers' => $this->modifiers,
            'modifiers_total' => $this->modifiersTotal,
            'quantity' => $this->quantity,
            'line_total' => $this->lineTotal,
            'offer' => $this->offer ? ['name' => $this->offer->discount->name, 'amount' => $this->offer->amount, 'line_total' => $this->payable()] : null,
            'note' => $this->note,
            'problem' => $this->problem ? ['code' => $this->problem->errorCode, 'message' => $this->problem->getMessage()] : null,
        ];
    }
}
