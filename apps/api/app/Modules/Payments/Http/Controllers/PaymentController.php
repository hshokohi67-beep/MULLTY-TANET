<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\User;
use App\Modules\Payments\Actions\RecordRefund;
use App\Modules\Payments\Actions\RecordStaffPayment;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Enums\RefundMethod;
use App\Modules\Payments\Http\Requests\RefundRequest;
use App\Modules\Payments\Http\Requests\StaffPaymentRequest;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class PaymentController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],   // business date, tenant-local
            'to' => ['nullable', 'date_format:Y-m-d'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'status' => ['nullable', Rule::enum(PaymentAttemptStatus::class)],
            'branch_id' => ['nullable', 'string', 'max:26'],
        ]);

        return PaymentResource::collection(
            Payment::query()
                ->with(['order', 'refunds'])
                ->when($f['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
                ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when(($f['from'] ?? null) || ($f['to'] ?? null) || ($f['branch_id'] ?? null), fn ($q) => $q->whereIn('order_id', Order::query()->select('id')
                    ->when($f['from'] ?? null, fn ($o, $d) => $o->where('business_date', '>=', $d))
                    ->when($f['to'] ?? null, fn ($o, $d) => $o->where('business_date', '<=', $d))
                    ->when($f['branch_id'] ?? null, fn ($o, $id) => $o->where('branch_id', $id))))
                ->latest('created_at')->latest('id')
                ->cursorPaginate(50),
        );
    }

    public function forOrder(Order $order): AnonymousResourceCollection
    {
        return PaymentResource::collection(
            Payment::query()->where('order_id', $order->id)->with('refunds')->oldest('created_at')->oldest('id')->get(),
        );
    }

    public function store(StaffPaymentRequest $request, Order $order, RecordStaffPayment $record): JsonResponse
    {
        $v = $request->validated();
        /** @var User $user */
        $user = $request->user();

        $payment = $record->handle(
            $order,
            PaymentMethod::from($v['method']),
            isset($v['amount']) ? (int) $v['amount'] : null,
            $v['idempotency_key'],
            $user,
            $v['reference'] ?? null,
            $v['note'] ?? null,
        );

        return (new PaymentResource($payment->load('refunds')))
            ->additional(['message' => __('messages.payment_recorded')])
            ->response()
            ->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }

    public function refund(RefundRequest $request, Payment $payment, RecordRefund $record): JsonResponse
    {
        $v = $request->validated();
        /** @var User $user */
        $user = $request->user();

        $refund = $record->handle(
            $payment,
            (int) $v['amount'],
            RefundMethod::from($v['method']),
            $v['reason'],
            'refund:'.$v['idempotency_key'],
            $user,
            $v['reference'] ?? null,
        );

        return (new PaymentResource($payment->refresh()->load('refunds')))
            ->additional(['message' => __('messages.refund_recorded')])
            ->response()
            ->setStatusCode($refund->wasRecentlyCreated ? 201 : 200);
    }

    /** Today's takings by method (tenant-local business date), for the payments page header. */
    public function summary(Request $request, TenantContext $context): JsonResponse
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date']
            ?? CarbonImmutable::now($context->require()->timezone)->toDateString();

        $rows = Payment::query()
            ->where('status', PaymentAttemptStatus::Paid)
            ->whereIn('order_id', Order::query()->select('id')->where('business_date', $date))
            ->get(['method', 'amount', 'refunded_amount'])
            ->groupBy(fn (Payment $p) => $p->method->value);

        return response()->json(['data' => [
            'date' => $date,
            'methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => [
                'method' => $m->value,
                'label' => $m->label(),
                'count' => $rows->get($m->value)?->count() ?? 0,
                'amount' => (int) ($rows->get($m->value)?->sum('amount') ?? 0),
                'refunded' => (int) ($rows->get($m->value)?->sum('refunded_amount') ?? 0),
            ])->values(),
        ]]);
    }
}
