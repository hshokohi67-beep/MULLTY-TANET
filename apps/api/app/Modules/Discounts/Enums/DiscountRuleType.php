<?php

namespace App\Modules\Discounts\Enums;

/**
 * Restrictions on where a discount applies. Rules of the same type are alternatives (OR);
 * different types must all hold (AND). No rules = applies everywhere.
 * `product`/`category` rules also decide *which lines* an "items" discount reduces.
 */
enum DiscountRuleType: string
{
    case Product = 'product';
    case Category = 'category';
    case Branch = 'branch';
    case OrderType = 'order_type';
    case Customer = 'customer';
    case Tier = 'tier'; // loyalty tier (resolved through CustomerTierLookup)
}
