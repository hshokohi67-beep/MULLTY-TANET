<?php

namespace Tests\Feature\Kitchen;

use App\Modules\Commerce\Events\OrderCompleted;
use App\Modules\Commerce\Models\Order;
use App\Modules\Kitchen\Actions\RouteOrderToKitchen;
use App\Modules\Kitchen\Events\KitchenBoardChanged;
use App\Modules\Kitchen\Models\KitchenEvent;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTier;
use Illuminate\Support\Facades\Event;

final class KitchenFlowTest extends KitchenTestCase
{
    public function test_order_lines_are_routed_to_their_stations_once(): void
    {
        $order = $this->mixedOrder();
        [$latte, $espresso] = $this->items($order);

        $this->assertSame($this->bar->id, $latte->station_id);     // default station
        $this->assertSame($this->kitchen->id, $espresso->station_id); // explicit route
        $this->assertSame(2, $espresso->quantity);
        $this->assertSame('queued', $latte->status->value);

        $this->inTenant($this->tenant, fn () => app(RouteOrderToKitchen::class)->handle(Order::query()->findOrFail($order)));
        $this->assertCount(2, $this->items($order));
    }

    public function test_a_branch_without_stations_has_no_kds_and_ordering_still_works(): void
    {
        $this->inTenant($this->tenant, fn () => KitchenStation::query()->update(['is_active' => false]));

        $order = $this->mixedOrder();

        $this->assertSame([], $this->items($order));
    }

    public function test_online_orders_reach_the_kitchen_only_after_payment(): void
    {
        $this->enableOnline();
        [, $token] = $this->customer();
        $order = $this->customerOrder($token, paymentMethod: 'online');
        $this->assertSame([], $this->items($order));

        $payment = $this->postJson("/api/v1/public/orders/{$order}/pay", [], $this->publicHeaders(['X-Order-Token' => $this->inTenant($this->tenant, fn () => Order::query()->findOrFail($order)->trackingToken())]))->json('data.payment_id');
        $this->verify($payment, $this->authorityOf($payment))->assertJsonPath('data.status', 'paid');

        $this->assertCount(1, $this->items($order));
    }

    public function test_kitchen_progress_drives_the_order_and_auto_completes_dine_in(): void
    {
        Event::fake([OrderCompleted::class, KitchenBoardChanged::class]);
        $order = $this->mixedOrder();
        [$latte, $espresso] = $this->items($order);

        $this->act($latte->id, 'start')->assertOk()->assertJsonPath('data.order_status', 'preparing');
        $this->act($latte->id, 'ready')->assertOk()->assertJsonPath('data.order_status', 'preparing');
        $this->act($espresso->id, 'ready')->assertOk()->assertJsonPath('data.order_status', 'completed'); // queued → ready directly

        Event::assertDispatched(OrderCompleted::class);
        Event::assertDispatched(KitchenBoardChanged::class);
        $history = collect($this->staff('GET', "/api/v1/orders/{$order}")->json('data.history'))->pluck('to')->all();
        $this->assertSame(['placed', 'accepted', 'preparing', 'ready', 'completed'], $history);

        $types = $this->inTenant($this->tenant, fn () => KitchenEvent::query()->where('order_id', $order)->orderBy('created_at')->orderBy('id')->pluck('type')->all());
        $this->assertSame(['routed', 'started', 'ready', 'ready'], $types);
    }

    public function test_takeaway_stays_ready_unless_the_tenant_auto_completes_it(): void
    {
        [, $token] = $this->customer();
        $order = $this->customerOrder($token);
        [$item] = $this->items($order);

        $this->act($item->id, 'ready')->assertJsonPath('data.order_status', 'ready');

        $this->settings(['kds.auto_complete_takeaway' => '1']);
        $second = $this->customerOrder($token);
        [$item2] = $this->items($second);
        $this->act($item2->id, 'ready')->assertJsonPath('data.order_status', 'completed');
    }

    public function test_bump_recall_and_invalid_steps(): void
    {
        $this->settings(['kds.auto_complete_dine_in' => '0']);
        $order = $this->mixedOrder();
        [$latte, $espresso] = $this->items($order);

        $this->postJson("/api/v1/kds/orders/{$order}/bump", ['station_id' => $this->kitchen->id], $this->kds())->assertOk()->assertJsonPath('data.order_status', 'preparing');
        $this->assertSame('ready', $this->items($order)[1]->status->value);

        // Recall while the order is still in progress.
        $this->act($espresso->id, 'recall')->assertOk()->assertJsonPath('data.status', 'preparing');

        $this->act($latte->id, 'ready');
        $this->act($espresso->id, 'ready')->assertJsonPath('data.order_status', 'ready');
        // Once the whole order is ready (handed over), recalling is refused.
        $this->act($espresso->id, 'recall')->assertUnprocessable()->assertJsonPath('code', 'kitchen_order_closed');
    }

    public function test_cancelling_an_order_cancels_its_kitchen_items_and_keeps_it_briefly_on_the_board(): void
    {
        $order = $this->mixedOrder();
        $this->staff('POST', "/api/v1/orders/{$order}/status", ['status' => 'cancelled', 'note' => 'مشتری رفت'])->assertOk();

        $this->assertSame(['cancelled', 'cancelled'], array_map(fn (KitchenItem $i) => $i->status->value, $this->items($order)));
        $this->board()->assertOk()->assertJsonPath('data.orders.0.state', 'cancelled');
        $this->act($this->items($order)[0]->id, 'start')->assertUnprocessable()->assertJsonPath('code', 'kitchen_order_closed');

        $this->travel(3)->minutes();
        $this->assertSame([], $this->board()->json('data.orders'));
    }

    public function test_finishing_an_order_at_the_counter_clears_the_board(): void
    {
        $order = $this->mixedOrder();
        foreach (['accepted', 'preparing', 'ready'] as $status) {
            $this->staff('POST', "/api/v1/orders/{$order}/status", ['status' => $status])->assertOk();
        }

        $this->assertSame(['ready', 'ready'], array_map(fn (KitchenItem $i) => $i->status->value, $this->items($order)));
    }

    public function test_board_content_station_filter_tier_calls_and_etag(): void
    {
        $tier = $this->inTenant($this->tenant, fn () => LoyaltyTier::query()->create(['name' => 'طلایی', 'min_spend' => 0]));
        [$customer, $token] = $this->customer();
        $this->inTenant($this->tenant, fn () => LoyaltyAccount::for($customer)->forceFill(['tier_id' => $tier->id])->save());
        $this->customerOrder($token);
        $this->mixedOrder(); // also opens a table session…
        $this->postJson('/api/v1/public/tables/requests', ['type' => 'call_waiter'], $this->publicHeaders(['X-Table-Session' => $this->joinTable()]))->assertCreated();

        $board = $this->board()->assertOk();
        $board->assertJsonCount(2, 'data.orders')
            ->assertJsonPath('data.orders.0.tier', 'طلایی')
            ->assertJsonPath('data.orders.1.table', 'میز ۱')
            ->assertJsonPath('data.orders.1.note', 'فوری')
            ->assertJsonPath('data.orders.1.items.0.modifiers.0', 'شیر بادام')
            ->assertJsonPath('data.table_requests.0.type_label', 'صدا زدن گارسون')
            ->assertJsonPath('data.stations.1.late_after_minutes', 12);
        $this->assertNotNull($board->json('data.server_time'));

        // Only the bar's lines.
        $bar = $this->board(query: "?station_id={$this->bar->id}")->json('data.orders');
        $this->assertCount(1, $bar);
        $this->assertSame('بار قهوه', $bar[0]['items'][0]['station']);

        // Unchanged board → 304.
        $etag = $board->headers->get('ETag');
        $this->getJson('/api/v1/kds/board', [...$this->kds(), 'If-None-Match' => $etag])->assertStatus(304);

        $callId = $board->json('data.table_requests.0.id');
        $this->postJson("/api/v1/kds/table-requests/{$callId}/acknowledge", [], $this->kds())->assertOk()->assertJsonPath('data.status', 'acknowledged');
        $this->assertSame([], $this->board()->json('data.table_requests'));
    }
}
