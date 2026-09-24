<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Exceptions\AddressLimitException;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a customer's address. Exactly one address is the default:
 * the first one automatically, afterwards whichever the customer marks.
 */
final class SaveCustomerAddress
{
    /**
     * @param  array<string, mixed>  $attributes  validated
     */
    public function handle(Customer $customer, array $attributes, ?CustomerAddress $address = null): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $attributes, $address): CustomerAddress {
            $existing = CustomerAddress::query()->where('customer_id', $customer->getKey())->lockForUpdate()->count();

            if ($address === null && $existing >= CustomerAddress::MAX_PER_CUSTOMER) {
                throw new AddressLimitException;
            }

            $address ??= new CustomerAddress(['customer_id' => $customer->getKey()]);
            $makeDefault = (bool) ($attributes['is_default'] ?? false) || $existing === 0;
            unset($attributes['is_default']);

            $address->fill($attributes);
            $address->is_default = $makeDefault || ($address->exists && $address->is_default);
            $address->save();

            if ($address->is_default) {
                CustomerAddress::query()->where('customer_id', $customer->getKey())->whereKeyNot($address->getKey())->update(['is_default' => false]);
            }

            return $address;
        });
    }

    public function delete(Customer $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            $wasDefault = $address->is_default;
            $address->delete(); // soft: past orders keep their own snapshot anyway

            if ($wasDefault) {
                CustomerAddress::query()->where('customer_id', $customer->getKey())->latest()->first()?->update(['is_default' => true]);
            }
        });
    }
}
