<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Models\Order;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountUsage;
use Carbon\CarbonImmutable;

/**
 * Menu offers («نمایش روی منو»): the discounted price on a card is exactly what the cart charges,
 * even with several offers in one cart and a coupon on top.
 */
final class MenuOffersTest extends CommerceTestCase
{
    /** @param  array<string, mixed>  $data */
    private function offer(array $data): string
    {
        return $this->postJson('/api/v1/discounts', [
            'name' => 'پیشنهاد', 'kind' => 'percent', 'value' => 1000, 'applies_to' => 'items', 'show_on_menu' => true, ...$data,
        ], $this->staffHeaders($this->owner, $this->tenant))->assertCreated()->json('data.id');
    }

    /** @return array<string, mixed> */
    private function menu(): array
    {
        return $this->getJson('/api/v1/public/menu?branch=main', $this->publicHeaders())->assertOk()->json('data');
    }

    public function test_two_offers_in_one_cart_both_hold_and_a_coupon_adds_on_top(): void
    {
        $this->offer(['name' => 'اسپرسوی ارزان', 'value' => 1500, 'rules' => ['product_ids' => [$this->espresso->id]]]);
        $this->offer(['name' => 'لاته', 'kind' => 'fixed', 'value' => 100_000, 'rules' => ['product_ids' => [$this->latte->id]]]);
        $this->postJson('/api/v1/discounts', ['name' => 'کد', 'code' => 'MORE', 'kind' => 'percent', 'value' => 1000, 'applies_to' => 'order'], $this->staffHeaders($this->owner, $this->tenant))->assertCreated();

        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso), 2);                                      // 2 × 650,000
        $this->addItem($cart, $this->variant($this->latte), 1, [$this->milkOption('شیر بادام')]);        // 850,000 + 250,000
        $quote = $this->getJson('/api/v1/public/cart?coupon_code=MORE', $this->publicHeaders(['X-Cart-Token' => $cart]))->assertOk()->json('data.quote');

        // 15% of 650,000 per espresso (the card shows 552,500), 100,000 off the latte.
        $lines = collect($quote['lines'])->keyBy('product_name');
        $this->assertSame(195_000, $lines['اسپرسو']['offer']['amount']);
        $this->assertSame(1_105_000, $lines['اسپرسو']['offer']['line_total']);
        $this->assertSame(100_000, $lines['لاته']['offer']['amount']);
        $this->assertSame([['name' => 'اسپرسوی ارزان', 'amount' => 195_000], ['name' => 'لاته', 'amount' => 100_000]], $quote['offers']);

        // The coupon works on what is left: 10% of 2,105,000.
        $this->assertSame(210_500, $quote['discount']['amount']);
        $this->assertSame(505_500, $quote['discount_total']);
        $this->assertSame(2_400_000 - 505_500, $quote['total']);

        // The menu promises the same price.
        $offer = collect($this->menu()['deals']['offers'])->firstWhere('label', 'اسپرسوی ارزان');
        $this->assertSame([$this->espresso->id], $offer['product_ids']);
        $this->assertSame(['from' => null, 'to' => null], $offer['windows'][0]);
        $this->assertSame(650_000 - intdiv(650_000 * $offer['value'] + 5000, 10000), 552_500);

        // Checkout freezes it: one usage per discount, the offers in the snapshot.
        $order = $this->checkout($cart, ['coupon_code' => 'MORE'])->assertCreated()
            ->assertJsonPath('data.discount_total', 505_500)
            ->assertJsonPath('data.discount.offers.0.name', 'اسپرسوی ارزان')
            ->json('data');
        $this->inTenant($this->tenant, function () use ($order): void {
            $this->assertSame(3, DiscountUsage::query()->where('order_id', $order['id'])->count());
            $this->assertSame(1, Discount::query()->where('name', 'لاته')->value('used_count'));
            $this->assertCount(2, Order::query()->findOrFail($order['id'])->discount_snapshot['offers']);
        });
    }

    public function test_an_offer_alone_names_the_order_discount_and_beats_nothing_else(): void
    {
        $this->offer(['name' => 'ده درصد اسپرسو', 'rules' => ['product_ids' => [$this->espresso->id]]]);
        // An ordinary automatic discount still competes for the order's one discount.
        $this->postJson('/api/v1/discounts', ['name' => 'ثابت', 'kind' => 'fixed', 'value' => 50_000, 'applies_to' => 'order'], $this->staffHeaders($this->owner, $this->tenant))->assertCreated();

        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso));
        $quote = $this->getJson('/api/v1/public/cart', $this->publicHeaders(['X-Cart-Token' => $cart]))->json('data.quote');

        $this->assertSame(65_000, $quote['lines'][0]['offer']['amount']);
        $this->assertSame('ثابت', $quote['discount']['name']);
        $this->assertSame(650_000 - 65_000 - 50_000, $quote['total']);
    }

    public function test_menu_offers_must_be_unconditional(): void
    {
        $h = $this->staffHeaders($this->owner, $this->tenant);
        $base = ['name' => 'x', 'kind' => 'percent', 'value' => 1000, 'show_on_menu' => true];

        $this->postJson('/api/v1/discounts', [...$base, 'applies_to' => 'order'], $h)->assertUnprocessable()->assertJsonValidationErrors('show_on_menu');
        $this->postJson('/api/v1/discounts', [...$base, 'applies_to' => 'items', 'code' => 'ABC', 'rules' => ['product_ids' => [$this->espresso->id]]], $h)->assertUnprocessable()->assertJsonValidationErrors('show_on_menu');
        $this->postJson('/api/v1/discounts', [...$base, 'applies_to' => 'items', 'min_order' => 10_000, 'rules' => ['product_ids' => [$this->espresso->id]]], $h)->assertUnprocessable()->assertJsonValidationErrors('show_on_menu');
        $this->postJson('/api/v1/discounts', [...$base, 'applies_to' => 'items', 'rules' => ['product_ids' => [$this->espresso->id], 'order_types' => ['delivery']]], $h)->assertUnprocessable()->assertJsonValidationErrors('show_on_menu');
        $this->postJson('/api/v1/discounts', [...$base, 'applies_to' => 'items', 'rules' => ['product_ids' => [$this->espresso->id]]], $h)->assertCreated();
    }

    public function test_windows_follow_the_schedule_like_the_engine(): void
    {
        // Saturday nights 22:00–02:00 (Tehran). The engine checks the weekday of the moment, so
        // after midnight (Sunday) the offer is off: the window ends at midnight too.
        $this->offer(['name' => 'شب شنبه', 'rules' => ['product_ids' => [$this->espresso->id]], 'schedule' => ['weekdays' => [6], 'from' => '22:00', 'to' => '02:00']]);

        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Tehran')); // Saturday noon
        $windows = $this->menu()['deals']['offers'][0]['windows'];
        $this->assertSame([['from' => '2026-09-26T18:30:00+00:00', 'to' => '2026-09-26T20:30:00+00:00']], $windows);

        $cart = $this->cart('takeaway');
        $this->addItem($cart, $this->variant($this->espresso));
        $price = fn () => $this->getJson('/api/v1/public/cart', $this->publicHeaders(['X-Cart-Token' => $cart]))->json('data.quote.lines.0.offer');
        $this->assertNull($price());

        $this->travelTo(CarbonImmutable::parse('2026-09-26 23:00', 'Asia/Tehran'));
        $this->assertSame(65_000, $price()['amount']);

        $this->travelTo(CarbonImmutable::parse('2026-09-27 01:00', 'Asia/Tehran')); // Sunday
        $this->assertNull($price());
        $this->assertSame([], $this->menu()['deals']['offers']); // nothing more within the next days
    }

    public function test_specials_are_whole_days_in_the_cafe_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Tehran')); // Saturday
        $h = $this->staffHeaders($this->owner, $this->tenant);
        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}", ['name' => 'اسپرسو', 'special_days' => [6, 7]], $h)->assertOk()->assertJsonPath('data.special_days', [6, 7]);
        $this->putJson("/api/v1/catalog/products/{$this->latte->id}", ['name' => 'لاته', 'special_days' => [1, 2, 3, 4, 5, 6, 7]], $h)->assertOk();

        $specials = collect($this->menu()['deals']['specials'])->keyBy('product_id');
        $this->assertSame([['from' => '2026-09-25T20:30:00+00:00', 'to' => '2026-09-27T20:30:00+00:00']], $specials[$this->espresso->id]['windows']);
        $this->assertSame([['from' => null, 'to' => null]], $specials[$this->latte->id]['windows']);

        // Leaving special_days out keeps them; [] clears them.
        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}", ['name' => 'اسپرسو'], $h)->assertOk()->assertJsonPath('data.special_days', [6, 7]);
        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}", ['name' => 'اسپرسو', 'special_days' => []], $h)->assertOk()->assertJsonPath('data.special_days', []);
    }
}
