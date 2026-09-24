<?php

namespace Tests\Feature\Payments;

use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Core\Models\AuditLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

final class StaffPaymentTest extends PaymentsTestCase
{
    private function record(string $orderId, array $body, ?array $headers = null): TestResponse
    {
        return $this->postJson("/api/v1/orders/{$orderId}/payments", ['idempotency_key' => uniqid('k', true), ...$body], $headers ?? $this->staffHeaders($this->owner, $this->tenant));
    }

    private function refund(string $paymentId, array $body, ?array $headers = null): TestResponse
    {
        return $this->postJson("/api/v1/payments/{$paymentId}/refunds", ['idempotency_key' => uniqid('r', true), 'method' => 'cash', 'reason' => 'اشتباه در سفارش', ...$body], $headers ?? $this->staffHeaders($this->owner, $this->tenant));
    }

    private function order(string $id): TestResponse
    {
        return $this->getJson("/api/v1/orders/{$id}", $this->staffHeaders($this->owner, $this->tenant));
    }

    public function test_partial_then_full_payment_at_the_counter(): void
    {
        $order = $this->quickQrOrder()->json('data.id'); // 650,000 rial

        $this->record($order, ['method' => 'cash', 'amount' => 300_000])->assertCreated()
            ->assertJsonPath('data.method_label', 'نقدی')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('message', 'پرداخت ثبت شد.');
        $this->order($order)->assertJsonPath('data.payment_status', 'partially_paid')->assertJsonPath('data.remaining_due', 350_000);

        // No amount = whatever is left.
        $this->record($order, ['method' => 'card_pos', 'reference' => '۱۲۳۴۵۶'])->assertCreated()
            ->assertJsonPath('data.amount', 350_000)
            ->assertJsonPath('data.reference', '123456');
        $this->order($order)->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.remaining_due', 0);

        $this->record($order, ['method' => 'cash'])->assertUnprocessable()->assertJsonPath('code', 'payment_nothing_due');
        $this->assertSame(2, $this->inTenant($this->tenant, fn () => AuditLog::query()->where('action', 'payment.recorded')->count()));
    }

    public function test_payments_cannot_exceed_the_remaining_amount_and_are_idempotent(): void
    {
        $order = $this->quickQrOrder()->json('data.id');
        $other = $this->quickQrOrder()->json('data.id');

        $this->record($order, ['method' => 'cash', 'amount' => 700_000])->assertUnprocessable()->assertJsonPath('code', 'payment_exceeds_remaining');

        $first = $this->record($order, ['method' => 'cash', 'amount' => 100_000, 'idempotency_key' => 'same'])->assertCreated()->json('data.id');
        $this->record($order, ['method' => 'cash', 'amount' => 100_000, 'idempotency_key' => 'same'])->assertOk()->assertJsonPath('data.id', $first);
        $this->order($order)->assertJsonPath('data.paid_total', 100_000);

        $this->record($other, ['method' => 'cash', 'idempotency_key' => 'same'])->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $this->order($other)->assertJsonPath('data.paid_total', 0);
    }

    public function test_no_payments_on_cancelled_orders(): void
    {
        $order = $this->quickQrOrder()->json('data.id');
        $this->postJson("/api/v1/orders/{$order}/status", ['status' => 'rejected', 'note' => 'تمام شده'], $this->staffHeaders($this->owner, $this->tenant))->assertOk();

        $this->record($order, ['method' => 'cash'])->assertUnprocessable()->assertJsonPath('code', 'payment_order_closed');
    }

    public function test_paying_at_the_counter_releases_an_order_waiting_for_online_payment(): void
    {
        Event::fake([OrderPlaced::class]);
        $this->enableOnline();
        $order = $this->onlineOrder();

        $this->record($order['id'], ['method' => 'cash'])->assertCreated();

        $this->order($order['id'])->assertJsonPath('data.status', 'placed')->assertJsonPath('data.payment_status', 'paid');
        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    public function test_refunds_are_limited_to_what_was_paid_and_update_the_order(): void
    {
        $order = $this->quickQrOrder()->json('data.id');
        $payment = $this->record($order, ['method' => 'cash'])->json('data.id');

        $this->refund($payment, ['amount' => 200_000])->assertCreated()
            ->assertJsonPath('data.refunded_amount', 200_000)
            ->assertJsonPath('data.refundable', 450_000)
            ->assertJsonPath('data.refunds.0.reason', 'اشتباه در سفارش')
            ->assertJsonPath('message', 'بازگشت وجه ثبت شد.');
        $this->order($order)->assertJsonPath('data.payment_status', 'partially_refunded')->assertJsonPath('data.remaining_due', 200_000);

        $this->refund($payment, ['amount' => 450_001])->assertUnprocessable()->assertJsonPath('code', 'refund_exceeds');
        $this->refund($payment, ['amount' => 450_000, 'method' => 'card', 'reference' => 'TR-9'])->assertCreated();
        $this->order($order)->assertJsonPath('data.payment_status', 'refunded');
        $this->refund($payment, ['amount' => 1])->assertUnprocessable()->assertJsonPath('code', 'refund_not_allowed');

        $this->assertSame(2, $this->inTenant($this->tenant, fn () => AuditLog::query()->where('action', 'payment.refunded')->count()));
    }

    public function test_failed_online_attempts_cannot_be_refunded(): void
    {
        $this->enableOnline();
        $this->useZarinpal(verify: ['data' => [], 'errors' => ['code' => -51, 'message' => 'x']]);
        $order = $this->onlineOrder();
        $payment = $this->pay($order)->json('data.payment_id');
        $this->verify($payment, $this->authorityOf($payment));

        $this->refund($payment, ['amount' => 1000, 'method' => 'gateway_panel'])->assertUnprocessable()->assertJsonPath('code', 'refund_not_allowed');
    }

    public function test_permissions(): void
    {
        $order = $this->quickQrOrder()->json('data.id');
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $kitchen = $this->addMember($this->tenant, $this->owner, 'kitchen');

        $payment = $this->record($order, ['method' => 'cash'], $this->staffHeaders($cashier, $this->tenant))->assertCreated()->json('data.id');
        $this->refund($payment, ['amount' => 1000], $this->staffHeaders($cashier, $this->tenant))->assertForbidden();

        $this->getJson("/api/v1/orders/{$order}/payments", $this->staffHeaders($kitchen, $this->tenant))->assertForbidden();
        $this->getJson('/api/v1/payments', $this->staffHeaders($kitchen, $this->tenant))->assertForbidden();
        $this->record($order, ['method' => 'cash'], $this->staffHeaders($kitchen, $this->tenant))->assertForbidden();
    }

    public function test_payments_list_and_daily_summary(): void
    {
        $order = $this->quickQrOrder()->json('data.id');
        $this->record($order, ['method' => 'cash', 'amount' => 400_000]);
        $this->record($order, ['method' => 'card_pos']);
        $headers = $this->staffHeaders($this->owner, $this->tenant);

        $this->getJson('/api/v1/payments', $headers)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.order.daily_number', 1);
        $this->getJson('/api/v1/payments?method=cash', $headers)->assertJsonCount(1, 'data');

        $methods = collect($this->getJson('/api/v1/payments/summary', $headers)->assertOk()->json('data.methods'))->keyBy('method');
        $this->assertSame(400_000, $methods['cash']['amount']);
        $this->assertSame(250_000, $methods['card_pos']['amount']);
        $this->assertSame(0, $methods['online']['count']);
    }
}
