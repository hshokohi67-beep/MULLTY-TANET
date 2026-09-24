<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Commerce\Actions\Carts\ManageCart;
use App\Modules\Commerce\Actions\Orders\PlaceOrder;
use App\Modules\Commerce\Contracts\OnlinePaymentGate;
use App\Modules\Commerce\Data\CheckoutData;
use App\Modules\Commerce\Enums\OrderSource;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Http\Requests\CheckoutRequest;
use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\OptionalCustomer;
use App\Modules\Customers\Models\CustomerAddress;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class CheckoutController
{
    public function store(CheckoutRequest $request, ManageCart $carts, PlaceOrder $place, OnlinePaymentGate $payments): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');

        if ($key === '' || strlen($key) > 80) {
            throw CommerceException::idempotencyKeyRequired();
        }

        $cart = $carts->find($request->header('X-Cart-Token'), allowConverted: true);

        // A converted cart may only be used to replay the checkout that converted it.
        if ($cart->status === 'converted' && ! Order::query()->where('idempotency_key', $key)->exists()) {
            throw CommerceException::cartInvalid();
        }

        $customer = OptionalCustomer::from($request);
        $v = $request->validated();

        // Guests may only order at a table; takeaway/delivery need a verified phone number.
        if ($customer === null && $cart->order_type !== OrderType::QrTable) {
            throw CommerceException::loginRequired();
        }

        $method = $v['payment_method'] ?? 'cash';

        if ($method === 'online' && ! $payments->onlineAvailable()) {
            throw CommerceException::onlinePaymentUnavailable();
        }

        $address = null;
        if ($cart->order_type->needsAddress()) {
            $address = $customer && ! empty($v['address_id'])
                ? CustomerAddress::query()->where('customer_id', $customer->getKey())->find($v['address_id'])
                : null;

            if ($address === null) {
                throw CommerceException::addressRequired();
            }
        }

        ['order' => $order, 'replayed' => $replayed] = $place->handle(new CheckoutData(
            branch: $cart->branch,
            type: $cart->order_type,
            source: $cart->order_type === OrderType::QrTable ? OrderSource::Qr : OrderSource::Web,
            lines: $carts->lines($cart),
            idempotencyKey: $key,
            customer: $customer,
            address: $address,
            session: $cart->session,
            contactName: $v['contact_name'] ?? null,
            contactPhoneE164: $request->contactPhoneE164(),
            note: $v['note'] ?? null,
            scheduledFor: ! empty($v['scheduled_for']) ? CarbonImmutable::parse($v['scheduled_for']) : null,
            couponCode: $v['coupon_code'] ?? null,
            paymentMethodIntent: $method,
            cartId: $cart->id,
        ));

        return (new OrderResource($order->load(['items.modifiers', 'branch', 'table'])))
            ->additional(['tracking_token' => $order->trackingToken(), 'replayed' => $replayed])
            ->response()
            ->setStatusCode($replayed ? 200 : 201);
    }
}
