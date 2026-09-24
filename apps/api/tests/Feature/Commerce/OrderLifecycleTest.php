<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Events\OrderCompleted;
use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;

final class OrderLifecycleTest extends CommerceTestCase
{
    public function test_full_lifecycle_with_history_and_events(): void
    {
        Event::fake([OrderPlaced::class, OrderStatusChanged::class, OrderCompleted::class]);
        $order = $this->quickQrOrder()->json('data');
        Event::assertDispatched(OrderPlaced::class);

        $kitchen = $this->addMember($this->tenant, $this->owner, 'kitchen');
        $h = $this->staffHeaders($kitchen, $this->tenant);

        $this->assertSame(['accepted', 'rejected', 'cancelled'], array_column($order['next_statuses'], 'value'));

        foreach (['accepted', 'preparing', 'ready', 'completed'] as $status) {
            $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => $status], $h)->assertOk()->assertJsonPath('data.status', $status);
        }

        $detail = $this->getJson("/api/v1/orders/{$order['id']}", $h)->assertOk()
            ->assertJsonPath('data.status_label', 'تحویل شد')
            ->assertJsonPath('data.next_statuses', []);

        $this->assertSame(['placed', 'accepted', 'preparing', 'ready', 'completed'], array_column($detail->json('data.history'), 'to'));
        $this->assertNotNull($detail->json('data.completed_at'));
        Event::assertDispatchedTimes(OrderStatusChanged::class, 4);
        Event::assertDispatchedTimes(OrderCompleted::class, 1);
    }

    public function test_invalid_transitions_are_refused_in_persian(): void
    {
        $order = $this->quickQrOrder()->json('data.id');
        $h = $this->staffHeaders($this->owner, $this->tenant);

        $this->postJson("/api/v1/orders/{$order}/status", ['status' => 'completed'], $h)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_transition')
            ->assertJsonPath('message', 'وضعیت سفارش از «ثبت شد» به «تحویل شد» قابل تغییر نیست.');

        // Cancelling needs a reason.
        $this->postJson("/api/v1/orders/{$order}/status", ['status' => 'cancelled'], $h)->assertJsonValidationErrors('note');
        $this->postJson("/api/v1/orders/{$order}/status", ['status' => 'cancelled', 'note' => 'مشتری منصرف شد'], $h)
            ->assertOk()->assertJsonPath('data.cancel_reason', 'مشتری منصرف شد');

        // Final states are final.
        $this->postJson("/api/v1/orders/{$order}/status", ['status' => 'accepted'], $h)->assertUnprocessable();
    }

    public function test_delivery_orders_go_out_for_delivery(): void
    {
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $address = $this->postJson('/api/v1/customer/addresses', ['title' => 'خانه', 'city' => 'تهران', 'address' => 'x', 'latitude' => 35.7610, 'longitude' => 51.4120], $this->publicHeaders($auth))->json('data.id');
        $cart = $this->cart('delivery', $auth);
        $this->addItem($cart, $this->variant($this->espresso), 2, headers: $auth);
        $order = $this->checkout($cart, ['address_id' => $address], $auth)->assertCreated()->json('data.id');

        $h = $this->staffHeaders($this->owner, $this->tenant);
        foreach (['accepted', 'preparing', 'ready'] as $s) {
            $this->postJson("/api/v1/orders/{$order}/status", ['status' => $s], $h)->assertOk();
        }
        $this->postJson("/api/v1/orders/{$order}/status", ['status' => 'completed'], $h)->assertUnprocessable();
        $this->postJson("/api/v1/orders/{$order}/status", ['status' => 'out_for_delivery'], $h)->assertOk();
        $this->postJson("/api/v1/orders/{$order}/status", ['status' => 'completed'], $h)->assertOk();

        // The customer sees it in their history.
        $this->getJson('/api/v1/customer/orders', $this->publicHeaders($auth))->assertOk()->assertJsonPath('data.0.status', 'completed');
    }

    public function test_cashier_registers_a_counter_order_with_the_same_pricing(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');

        $this->postJson('/api/v1/orders', [
            'branch_id' => $this->branch->id,
            'type' => 'counter',
            'idempotency_key' => 'pos-1',
            'lines' => [
                ['variant_id' => $this->variant($this->latte), 'quantity' => 1, 'modifier_ids' => [$this->milkOption('شیر معمولی')]],
                ['variant_id' => $this->variant($this->espresso), 'quantity' => 2],
            ],
        ], $this->staffHeaders($cashier, $this->tenant))
            ->assertCreated()
            ->assertJsonPath('data.source', 'dashboard')
            ->assertJsonPath('data.total', 850_000 + 1_300_000)
            ->assertJsonPath('data.history.0.to', 'placed');

        // Kitchen staff can move orders along but not create them.
        $kitchen = $this->addMember($this->tenant, $this->owner, 'kitchen');
        $this->postJson('/api/v1/orders', ['branch_id' => $this->branch->id, 'type' => 'counter', 'idempotency_key' => 'x', 'lines' => []], $this->staffHeaders($kitchen, $this->tenant))->assertForbidden();
    }

    public function test_order_board_filters(): void
    {
        $open = $this->quickQrOrder()->json('data.id');
        $done = $this->quickQrOrder()->json('data.id');
        $h = $this->staffHeaders($this->owner, $this->tenant);
        $this->postJson("/api/v1/orders/{$done}/status", ['status' => 'rejected', 'note' => 'تمام شد'], $h)->assertOk();

        $ids = fn (string $q) => collect($this->getJson("/api/v1/orders?{$q}", $h)->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$open], $ids('status=open'));
        $this->assertSame([$done], $ids('status=rejected'));
        $this->assertEqualsCanonicalizing([$open, $done], $ids('type=qr_table'));
    }
}
