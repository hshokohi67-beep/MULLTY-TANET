<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Commerce\Actions\Orders\PlaceOrder;
use App\Modules\Commerce\Actions\Orders\TransitionOrder;
use App\Modules\Commerce\Data\CheckoutData;
use App\Modules\Commerce\Enums\OrderSource;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Http\Requests\OrderStatusRequest;
use App\Modules\Commerce\Http\Requests\StaffOrderRequest;
use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\OrderFilters;
use App\Modules\Core\Models\Branch;
use App\Support\Realtime\LiveVersion;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class OrderController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            OrderFilters::apply(OrderFilters::validate($request))
                ->with(['items.modifiers', 'branch', 'table'])
                ->latest('placed_at')->latest('id')
                ->cursorPaginate(50),
        );
    }

    /**
     * Totals for the same filters as the list (history header). Revenue counts orders that were
     * not cancelled, rejected or left unpaid online.
     */
    public function summary(Request $request): JsonResponse
    {
        $base = OrderFilters::apply(OrderFilters::validate($request));
        $counted = (clone $base)->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected, OrderStatus::PendingPayment]);
        $count = (clone $counted)->count();
        $revenue = (int) (clone $counted)->sum('total');

        return response()->json(['data' => [
            'orders' => (clone $base)->count(),
            'completed' => (clone $base)->where('status', OrderStatus::Completed)->count(),
            'cancelled' => (clone $base)->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])->count(),
            'revenue' => $revenue,
            'average' => $count > 0 ? (int) round($revenue / $count / 10) * 10 : 0, // whole toman
            'discounts' => (int) (clone $counted)->sum('discount_total'),
        ]]);
    }

    /**
     * "Did anything on the order board change?" — polled by open dashboards instead of reloading
     * the whole board. One cache read; 304 when unchanged.
     */
    public function liveVersion(Request $request, TenantContext $context): JsonResponse|Response
    {
        $version = LiveVersion::get(LiveVersion::orders($context->require()->id));

        if (trim((string) $request->header('If-None-Match'), '"') === $version) {
            return response()->noContent(304)->setEtag($version);
        }

        return response()->json(['data' => ['version' => $version]])->setEtag($version);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['items.modifiers', 'branch', 'table', 'history']));
    }

    public function transition(OrderStatusRequest $request, Order $order, TransitionOrder $transition): OrderResource
    {
        $order = $transition->handle(
            $order,
            OrderStatus::from($request->validated('status')),
            'user',
            $request->user()?->getAuthIdentifier(),
            $request->validated('note'),
        );

        return new OrderResource($order->load(['items.modifiers', 'branch', 'table', 'history']));
    }

    /** Counter / phone / dine-in orders registered by staff. Same pricer and snapshots as online orders. */
    public function store(StaffOrderRequest $request, PlaceOrder $place): JsonResponse
    {
        $v = $request->validated();
        $variants = ProductVariant::query()->whereKey(array_column($v['lines'], 'variant_id'))->pluck('product_id', 'id');
        $type = OrderType::from($v['type']);

        ['order' => $order, 'replayed' => $replayed] = $place->handle(new CheckoutData(
            branch: Branch::query()->findOrFail($v['branch_id']),
            type: $type,
            source: $type === OrderType::Phone ? OrderSource::Phone : OrderSource::Dashboard,
            lines: array_map(fn (array $line) => [
                'product_id' => (string) $variants[$line['variant_id']],
                'variant_id' => $line['variant_id'],
                'quantity' => (int) $line['quantity'],
                'modifier_ids' => $line['modifier_ids'] ?? [],
                'note' => $line['note'] ?? null,
            ], $v['lines']),
            idempotencyKey: 'staff:'.$v['idempotency_key'],
            contactName: $v['contact_name'] ?? null,
            contactPhoneE164: $request->contactPhoneE164(),
            note: $v['note'] ?? null,
            couponCode: $v['coupon_code'] ?? null,
            actorType: 'user',
            actorId: (string) $request->user()?->getAuthIdentifier(),
        ));

        return (new OrderResource($order->load(['items.modifiers', 'branch', 'table', 'history'])))
            ->response()
            ->setStatusCode($replayed ? 200 : 201);
    }
}
