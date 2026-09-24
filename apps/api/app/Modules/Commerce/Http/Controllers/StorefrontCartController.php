<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Commerce\Actions\Carts\ManageCart;
use App\Modules\Commerce\Actions\Carts\ReorderIntoCart;
use App\Modules\Commerce\Actions\Tables\TableSessions;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Http\Requests\CartItemRequest;
use App\Modules\Commerce\Http\Requests\CreateCartRequest;
use App\Modules\Commerce\Http\Requests\QuoteRequest;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\OptionalCustomer;
use App\Modules\Commerce\Support\Pricing\OrderPricer;
use App\Modules\Commerce\Support\Pricing\PricingRequest;
use App\Modules\Core\Models\Branch;
use App\Modules\Customers\Models\CustomerAddress;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Services are resolved per call (never held on the controller): Laravel reuses controller
 * instances, and they must never keep a previous request's tenant context.
 *
 * Carts are addressed by the X-Cart-Token header. Every read returns a fresh quote from
 * the pricer (live prices, discount, delivery), with per-line problems explained.
 */
final class StorefrontCartController
{
    private function carts(): ManageCart
    {
        return app(ManageCart::class);
    }

    public function store(CreateCartRequest $request, TableSessions $sessions): JsonResponse
    {
        $type = OrderType::from($request->validated('order_type'));
        $session = null;

        if ($type === OrderType::QrTable) {
            $session = $sessions->fromToken($request->header('X-Table-Session'));
            $branch = $session->table->branch;
        } else {
            $branch = Branch::query()->where('is_active', true)->findOrFail($request->validated('branch_id'));
        }

        ['cart' => $cart, 'token' => $token] = $this->carts()->create($branch, $type, $session, OptionalCustomer::from($request)?->getKey());

        return response()->json(['data' => ['cart_token' => $token, ...$this->quote($cart->load(['items', 'branch', 'session']), $request)]], 201);
    }

    public function show(QuoteRequest $request): JsonResponse
    {
        $cart = $this->carts()->find($request->header('X-Cart-Token'));

        return response()->json(['data' => $this->quote($cart, $request, $request->validated())]);
    }

    public function addItem(CartItemRequest $request): JsonResponse
    {
        $cart = $this->carts()->find($request->header('X-Cart-Token'));
        $v = $request->validated();
        $this->carts()->add($cart, $v['variant_id'], (int) $v['quantity'], $v['modifier_ids'] ?? [], $v['note'] ?? null);

        return response()->json(['data' => $this->quote($cart->refresh()->load(['items', 'branch', 'session']), $request)], 201);
    }

    public function updateItem(CartItemRequest $request, string $item): JsonResponse
    {
        $cart = $this->carts()->find($request->header('X-Cart-Token'));
        $this->carts()->update($cart, $item, (int) $request->validated('quantity'), $request->validated('note'));

        return response()->json(['data' => $this->quote($cart->refresh()->load(['items', 'branch', 'session']), $request)]);
    }

    public function removeItem(Request $request, string $item): JsonResponse
    {
        $cart = $this->carts()->find($request->header('X-Cart-Token'));
        $this->carts()->remove($cart, $item);

        return response()->json(['data' => $this->quote($cart->refresh()->load(['items', 'branch', 'session']), $request)]);
    }

    /** "Order again" from the signed-in customer's history; someone else's order looks missing. */
    public function reorder(Request $request, ReorderIntoCart $reorder): JsonResponse
    {
        $customer = OptionalCustomer::from($request) ?? throw CommerceException::loginRequired();
        $orderId = (string) $request->validate(['order_id' => ['required', 'string', 'size:26']])['order_id'];
        $order = Order::query()->where('customer_id', $customer->getKey())->find($orderId) ?? throw new NotFoundHttpException;
        $cart = $this->carts()->find($request->header('X-Cart-Token'));
        $skipped = $reorder->handle($order, $cart);

        return response()->json(['data' => [...$this->quote($cart->refresh()->load(['items', 'branch', 'session']), $request), 'skipped' => $skipped]]);
    }

    /**
     * @param  array{coupon_code?: ?string, address_id?: ?string, scheduled_for?: ?string}  $options
     * @return array<string, mixed>
     */
    private function quote(Cart $cart, Request $request, array $options = []): array
    {
        $customer = OptionalCustomer::from($request);
        $address = null;

        // Only the customer's own addresses can be quoted against.
        if (($options['address_id'] ?? null) && $customer !== null) {
            $address = CustomerAddress::query()->where('customer_id', $customer->getKey())->find($options['address_id']);
        }

        $priced = app(OrderPricer::class)->price(new PricingRequest(
            branch: $cart->branch,
            orderType: $cart->order_type,
            lines: $this->carts()->lines($cart),
            customerId: $customer?->getKey(),
            couponCode: $options['coupon_code'] ?? null,
            address: $address,
            scheduledFor: ! empty($options['scheduled_for']) ? CarbonImmutable::parse($options['scheduled_for']) : null,
        ), strict: false);

        return [
            'order_type' => $cart->order_type->value,
            'branch' => ['id' => $cart->branch->id, 'name' => $cart->branch->name],
            'table' => $cart->session ? ['label' => $cart->session->table->label] : null,
            'expires_at' => $cart->expires_at->toIso8601String(),
            'quote' => $priced->toArray(),
        ];
    }
}
