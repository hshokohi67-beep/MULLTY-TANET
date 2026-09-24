<?php

namespace App\Modules\Commerce\Data;

use App\Modules\Commerce\Enums\OrderSource;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Models\OrderSession;
use App\Modules\Core\Models\Branch;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use Carbon\CarbonImmutable;

final readonly class CheckoutData
{
    /**
     * @param  list<array{product_id: string, variant_id: string, quantity: int, modifier_ids?: ?list<string>, note?: ?string}>  $lines
     */
    public function __construct(
        public Branch $branch,
        public OrderType $type,
        public OrderSource $source,
        public array $lines,
        public string $idempotencyKey,
        public ?Customer $customer = null,
        public ?CustomerAddress $address = null,
        public ?OrderSession $session = null,
        public ?string $contactName = null,
        public ?string $contactPhoneE164 = null,
        public ?string $note = null,
        public ?CarbonImmutable $scheduledFor = null,
        public ?string $couponCode = null,
        public string $paymentMethodIntent = 'cash',
        public ?string $cartId = null,
        public ?string $actorType = null,
        public ?string $actorId = null,
    ) {}
}
