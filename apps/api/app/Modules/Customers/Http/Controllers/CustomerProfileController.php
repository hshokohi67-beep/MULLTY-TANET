<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Actions\UpdateCustomerProfile;
use App\Modules\Customers\Http\Requests\CustomerProfileRequest;
use App\Modules\Customers\Http\Resources\CustomerResource;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;

/** The signed-in customer's own profile. */
final class CustomerProfileController
{
    public function show(Request $request): CustomerResource
    {
        return new CustomerResource($this->customer($request));
    }

    public function update(CustomerProfileRequest $request, UpdateCustomerProfile $update): CustomerResource
    {
        $profile = $request->profile();
        unset($profile['staff_note']);

        return new CustomerResource($update->handle($this->customer($request), $profile, byStaff: false));
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user('sanctum');

        return $customer;
    }
}
