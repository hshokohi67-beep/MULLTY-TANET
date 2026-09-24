<?php

namespace Tests\Feature\Commerce;

use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Models\TenantSetting;
use Carbon\CarbonImmutable;

final class CartAndCheckoutTest extends CommerceTestCase
{
    public function test_guest_qr_order_end_to_end_with_modifiers_and_snapshots(): void
    {
        $session = $this->joinTable();
        $cart = $this->cart('qr_table', ['X-Table-Session' => $session]);

        $this->addItem($cart, $this->variant($this->latte, 1), 2, [$this->milkOption('شیر بادام')])
            ->assertCreated()
            ->assertJsonPath('data.quote.lines.0.unit_price', 1_050_000)
            ->assertJsonPath('data.quote.lines.0.modifiers_total', 250_000)
            ->assertJsonPath('data.quote.lines.0.line_total', 2_600_000);
        // Adding the same thing again merges the line.
        $this->addItem($cart, $this->variant($this->latte, 1), 1, [$this->milkOption('شیر بادام')])
            ->assertJsonCount(1, 'data.quote.lines')->assertJsonPath('data.quote.lines.0.quantity', 3);
        $this->addItem($cart, $this->variant($this->espresso))
            ->assertJsonPath('data.quote.subtotal', 3 * 1_300_000 + 650_000)
            ->assertJsonPath('data.quote.can_checkout', true)
            ->assertJsonPath('data.table.label', 'میز ۱');

        $order = $this->checkout($cart, ['note' => 'بدون شکر'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'qr_table')
            ->assertJsonPath('data.source', 'qr')
            ->assertJsonPath('data.status', 'placed')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.daily_number', 1)
            ->assertJsonPath('data.table.label', 'میز ۱')
            ->assertJsonPath('data.total', 4_550_000)
            ->assertJsonPath('data.items.0.modifiers.0.name', 'شیر بادام')
            ->json();

        // The menu changes afterwards; the order does not.
        $this->inTenant($this->tenant, fn () => Product::query()->whereKey($this->latte->id)->update(['name' => 'لاته جدید']));
        $this->getJson("/api/v1/public/orders/{$order['data']['id']}?token={$order['tracking_token']}", $this->publicHeaders())
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'لاته')
            ->assertJsonPath('data.items.0.unit_price', 1_050_000)
            ->assertJsonPath('data.history.0.to_label', 'ثبت شد');

        // The cart is spent.
        $this->addItem($cart, $this->variant($this->espresso))->assertNotFound()->assertJsonPath('code', 'cart_invalid');
    }

    public function test_tracking_requires_the_right_token(): void
    {
        $order = $this->quickQrOrder()->json('data.id');

        $this->getJson("/api/v1/public/orders/{$order}", $this->publicHeaders())->assertNotFound();
        $this->getJson("/api/v1/public/orders/{$order}?token=wrong", $this->publicHeaders())->assertNotFound();
    }

    public function test_daily_numbers_increase_per_branch(): void
    {
        $this->assertSame(1, $this->quickQrOrder()->json('data.daily_number'));
        $this->assertSame(2, $this->quickQrOrder()->json('data.daily_number'));

        $this->travel(1)->days();
        $this->assertSame(1, $this->quickQrOrder()->json('data.daily_number'));
    }

    public function test_checkout_is_idempotent_and_keys_cannot_be_hijacked(): void
    {
        $session = $this->joinTable();
        $cart = $this->cart('qr_table', ['X-Table-Session' => $session]);
        $this->addItem($cart, $this->variant($this->espresso));

        $first = $this->checkout($cart, key: 'key-1')->assertCreated();
        $replay = $this->checkout($cart, key: 'key-1')->assertOk()->assertJsonPath('replayed', true);

        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => Order::query()->count()));

        // Another buyer reusing the same key does not get the first order.
        [, $token] = $this->customer();
        $cart2 = $this->cart('takeaway', ['Authorization' => "Bearer {$token}"]);
        $this->addItem($cart2, $this->variant($this->espresso), headers: ['Authorization' => "Bearer {$token}"]);
        $this->checkout($cart2, headers: ['Authorization' => "Bearer {$token}"], key: 'key-1')->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');

        $this->postJson('/api/v1/public/checkout', [], $this->publicHeaders(['X-Cart-Token' => $cart]))->assertStatus(400)->assertJsonPath('code', 'idempotency_key_required');
    }

    public function test_modifier_rules_are_explained_in_the_cart_and_enforced_at_checkout(): void
    {
        $session = $this->joinTable();
        $cart = $this->cart('qr_table', ['X-Table-Session' => $session]);

        // Milk type is required: the line is kept but flagged.
        $this->addItem($cart, $this->variant($this->latte))
            ->assertCreated()
            ->assertJsonPath('data.quote.lines.0.problem.code', 'modifier_selection')
            ->assertJsonPath('data.quote.lines.0.problem.message', 'برای «لاته»، از «نوع شیر» دقیقاً ۱ مورد انتخاب کنید.')
            ->assertJsonPath('data.quote.can_checkout', false)
            ->assertJsonPath('data.quote.subtotal', 0);

        $this->checkout($cart)->assertUnprocessable()->assertJsonPath('code', 'modifier_selection');

        // A modifier from a group the product doesn't have is rejected.
        $foreign = $this->milkOption('شیر بادام');
        $this->addItem($cart, $this->variant($this->espresso), 1, [$foreign])->assertJsonPath('data.quote.lines.1.problem.code', 'modifier_invalid');
    }

    public function test_sold_out_items_cannot_be_ordered(): void
    {
        $session = $this->joinTable();
        $cart = $this->cart('qr_table', ['X-Table-Session' => $session]);
        $this->addItem($cart, $this->variant($this->espresso));

        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}/availability", ['branch_id' => $this->branch->id, 'status' => 'sold_out'], $this->staffHeaders($this->owner, $this->tenant))->assertOk();

        $this->getJson('/api/v1/public/cart', $this->publicHeaders(['X-Cart-Token' => $cart]))->assertJsonPath('data.quote.lines.0.problem.message', '«اسپرسو» در حال حاضر موجود نیست.');
        $this->checkout($cart)->assertUnprocessable()->assertJsonPath('code', 'product_unavailable');
    }

    public function test_client_cannot_influence_prices(): void
    {
        $session = $this->joinTable();
        $cart = $this->cart('qr_table', ['X-Table-Session' => $session]);
        $this->postJson('/api/v1/public/cart/items', ['variant_id' => $this->variant($this->espresso), 'quantity' => 1, 'price' => 1, 'unit_price' => 1], $this->publicHeaders(['X-Cart-Token' => $cart]))->assertCreated();

        $this->checkout($cart)->assertJsonPath('data.total', 650_000);
    }

    public function test_takeaway_and_delivery_need_a_logged_in_customer(): void
    {
        $cart = $this->cart('takeaway');
        $this->addItem($cart, $this->variant($this->espresso));

        $this->checkout($cart)->assertUnauthorized()->assertJsonPath('code', 'login_required');
    }

    public function test_delivery_zone_pipeline(): void
    {
        [$customer, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $address = fn (array $point) => $this->postJson('/api/v1/customer/addresses', ['title' => 'خانه', 'city' => 'تهران', 'address' => 'خیابان ملاصدرا', ...$point], $this->publicHeaders($auth))->assertCreated()->json('data.id');

        $near = $address(['latitude' => 35.7610, 'longitude' => 51.4120]);   // ~430 m
        $far = $address(['latitude' => 35.6892, 'longitude' => 51.3890]);    // ~7.8 km
        $noPin = $address([]);

        $cart = $this->cart('delivery', $auth);
        $this->addItem($cart, $this->variant($this->espresso), headers: $auth); // 650,000

        $quote = fn (string $id) => $this->getJson("/api/v1/public/cart?address_id={$id}", $this->publicHeaders(['X-Cart-Token' => $cart, ...$auth]))->assertOk()->json('data.quote');

        $this->assertSame(300_000, $quote($near)['delivery_fee']);
        $this->assertSame(950_000, $quote($near)['total']);
        $this->assertSame('out_of_delivery_area', $quote($far)['issues'][0]['code']);
        $this->assertSame('address_location_required', $quote($noPin)['issues'][0]['code']);

        $this->checkout($cart, [], $auth)->assertUnprocessable()->assertJsonPath('code', 'address_required');
        $this->checkout($cart, ['address_id' => $far], $auth)->assertUnprocessable()->assertJsonPath('message', 'متأسفانه این آدرس خارج از محدوده‌ی ارسال این شعبه است.');

        // Free delivery above 3,000,000 rial.
        $this->addItem($cart, $this->variant($this->espresso), 4, headers: $auth); // 5 × 650,000 = 3,250,000
        $order = $this->checkout($cart, ['address_id' => $near], $auth)
            ->assertCreated()
            ->assertJsonPath('data.delivery_fee', 0)
            ->assertJsonPath('data.total', 3_250_000)
            ->assertJsonPath('data.address.address', 'خیابان ملاصدرا')
            ->assertJsonPath('data.contact_phone', '09121111111')
            ->json('data');

        // Editing the address later doesn't touch the order's snapshot.
        $this->putJson("/api/v1/customer/addresses/{$near}", ['title' => 'خانه', 'city' => 'تهران', 'address' => 'آدرس جدید', 'latitude' => 35.76, 'longitude' => 51.41], $this->publicHeaders($auth))->assertOk();
        $this->getJson("/api/v1/orders/{$order['id']}", $this->staffHeaders($this->owner, $this->tenant))->assertJsonPath('data.address.address', 'خیابان ملاصدرا');
    }

    public function test_delivery_minimum_order(): void
    {
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $address = $this->postJson('/api/v1/customer/addresses', ['title' => 'خانه', 'city' => 'تهران', 'address' => 'x', 'latitude' => 35.7610, 'longitude' => 51.4120], $this->publicHeaders($auth))->json('data.id');

        $this->inTenant($this->tenant, fn () => DeliveryZone::query()->update(['min_order' => 1_000_000]));
        $cart = $this->cart('delivery', $auth);
        $this->addItem($cart, $this->variant($this->espresso), headers: $auth);

        $this->checkout($cart, ['address_id' => $address], $auth)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'حداقل مبلغ سفارش برای این آدرس ۱۰۰٬۰۰۰ تومان است.');
    }

    public function test_closed_branch_requires_a_valid_preorder_time(): void
    {
        // Open only 08:00–12:00 every day (Tehran time); it is now 20:00.
        $this->inTenant($this->tenant, function () {
            foreach (range(1, 7) as $day) {
                BranchOpeningHour::query()->create(['branch_id' => $this->branch->id, 'weekday' => $day, 'opens_at' => '08:00', 'closes_at' => '12:00']);
            }
        });
        $this->travelTo(CarbonImmutable::parse('2026-09-24 20:00', 'Asia/Tehran'));

        $session = $this->joinTable();
        $cart = $this->cart('qr_table', ['X-Table-Session' => $session]);
        $this->addItem($cart, $this->variant($this->espresso));

        $this->checkout($cart)->assertUnprocessable()->assertJsonPath('code', 'branch_closed');
        $this->checkout($cart, ['scheduled_for' => '2026-09-25T14:00:00+03:30'])->assertUnprocessable()->assertJsonPath('code', 'schedule_invalid');
        $scheduled = $this->checkout($cart, ['scheduled_for' => '2026-09-25T09:30:00+03:30'])->assertCreated()->json('data.scheduled_for');
        $this->assertTrue(CarbonImmutable::parse($scheduled)->equalTo(CarbonImmutable::parse('2026-09-25T09:30:00+03:30')));

        // With pre-orders disabled, closed means closed.
        $this->inTenant($this->tenant, fn () => TenantSetting::query()->create(['key' => 'orders.allow_preorder_when_closed', 'value' => '0']));
        $cart2 = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart2, $this->variant($this->espresso));
        $this->checkout($cart2)->assertUnprocessable()->assertJsonPath('code', 'preorder_disabled');
    }

    public function test_branch_prices_apply_to_the_cart(): void
    {
        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}/branch-prices", [
            'branch_id' => $this->branch->id,
            'prices' => [['variant_id' => $this->variant($this->espresso), 'amount' => 700_000]],
        ], $this->staffHeaders($this->owner, $this->tenant))->assertOk();

        $this->quickQrOrder()->assertJsonPath('data.total', 700_000);
    }

    public function test_inactive_branch_cannot_take_carts(): void
    {
        $other = $this->inTenant($this->tenant, fn () => Branch::query()->create(['name' => 'بسته', 'slug' => 'closed', 'is_active' => false]));

        $this->postJson('/api/v1/public/carts', ['order_type' => 'takeaway', 'branch_id' => $other->id], $this->publicHeaders())->assertNotFound();
    }
}
