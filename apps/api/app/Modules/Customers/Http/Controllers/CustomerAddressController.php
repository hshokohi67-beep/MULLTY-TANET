<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Actions\SaveCustomerAddress;
use App\Modules\Customers\Http\Requests\CustomerAddressRequest;
use App\Modules\Customers\Http\Resources\CustomerAddressResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * A customer only ever sees and edits their own addresses: every lookup is filtered
 * by the authenticated customer (on top of the tenant scope).
 */
final class CustomerAddressController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return CustomerAddressResource::collection(
            CustomerAddress::query()->where('customer_id', $this->customer($request)->getKey())->orderByDesc('is_default')->latest()->get(),
        );
    }

    public function store(CustomerAddressRequest $request, SaveCustomerAddress $save): JsonResponse
    {
        return (new CustomerAddressResource($save->handle($this->customer($request), $request->attributesForModel())))
            ->response()->setStatusCode(201);
    }

    public function update(CustomerAddressRequest $request, string $address, SaveCustomerAddress $save): CustomerAddressResource
    {
        $customer = $this->customer($request);

        return new CustomerAddressResource($save->handle($customer, $request->attributesForModel(), $this->own($customer, $address)));
    }

    public function destroy(Request $request, string $address, SaveCustomerAddress $save): Response
    {
        $customer = $this->customer($request);
        $save->delete($customer, $this->own($customer, $address));

        return response()->noContent();
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('sanctum');
    }

    private function own(Customer $customer, string $id): CustomerAddress
    {
        return CustomerAddress::query()->where('customer_id', $customer->getKey())->findOrFail($id);
    }
}
