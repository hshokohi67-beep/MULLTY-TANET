<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;

/**
 * Storefront endpoints work for guests too; when a valid customer token of *this* tenant
 * is present (the Sanctum callback enforces the tenant binding), it identifies the customer.
 * Staff tokens are ignored here.
 */
final class OptionalCustomer
{
    public static function from(Request $request): ?Customer
    {
        $actor = $request->user('sanctum');

        return $actor instanceof Customer && $actor->tokenCan('customer') ? $actor : null;
    }
}
