<?php

namespace Tests\Feature\Commerce;

use App\Modules\Catalog\Actions\SaveCategory;
use App\Modules\Discounts\Models\Discount;
use Carbon\CarbonImmutable;

final class DiscountTest extends CommerceTestCase
{
    private function createDiscount(array $data): string
    {
        return $this->postJson('/api/v1/discounts', [
            'name' => 'تخفیف',
            'kind' => 'percent',
            'value' => 1000,
            'applies_to' => 'order',
            ...$data,
        ], $this->staffHeaders($this->owner, $this->tenant))->assertCreated()->json('data.id');
    }

    /** A QR cart with 2 espressos (1,300,000 rial) and optional coupon; returns the quote. */
    private function quote(?string $coupon = null, int $espressos = 2): array
    {
        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso), $espressos);

        return $this->getJson('/api/v1/public/cart'.($coupon ? "?coupon_code={$coupon}" : ''), $this->publicHeaders(['X-Cart-Token' => $cart]))->assertOk()->json('data.quote');
    }

    public function test_best_automatic_discount_is_applied(): void
    {
        $this->createDiscount(['name' => 'ده درصد', 'value' => 1000]);
        $this->createDiscount(['name' => 'ثابت', 'kind' => 'fixed', 'value' => 200_000]);

        $quote = $this->quote();
        $this->assertSame('ثابت', $quote['discount']['name']); // 200,000 > 10% of 1,300,000
        $this->assertSame(1_100_000, $quote['total']);
    }

    public function test_percent_cap_and_minimum_order(): void
    {
        $this->createDiscount(['value' => 5000, 'max_discount' => 300_000, 'min_order' => 1_000_000]);

        $this->assertSame(300_000, $this->quote()['discount']['amount']); // 50% capped
        $this->assertNull($this->quote(espressos: 1)['discount']);       // 650,000 < minimum
    }

    public function test_coupon_code_is_case_insensitive_and_explains_failures(): void
    {
        $this->createDiscount(['name' => 'یلدا', 'code' => 'yalda', 'kind' => 'fixed', 'value' => 100_000, 'min_order' => 2_000_000]);

        $this->assertSame('coupon_min_order', $this->quote('YALDA')['issues'][0]['code']);
        $this->assertSame('این کد تخفیف برای سفارش‌های بالای ۲۰۰٬۰۰۰ تومان است.', $this->quote('Yalda')['issues'][0]['message']);
        $this->assertSame(100_000, $this->quote('yalda', 4)['discount']['amount']);
        $this->assertSame('coupon_invalid', $this->quote('NOPE')['issues'][0]['code']);
    }

    public function test_category_items_discount_only_reduces_matching_lines(): void
    {
        $coffee = $this->inTenant($this->tenant, fn () => app(SaveCategory::class)->handle(['name' => 'قهوه']));
        $this->inTenant($this->tenant, fn () => $this->espresso->categories()->attach($coffee->id, ['tenant_id' => $this->tenant->id, 'sort' => 0]));
        $this->createDiscount(['applies_to' => 'items', 'value' => 2000, 'rules' => ['category_ids' => [$coffee->id]]]);

        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso));                                          // 650,000 (coffee)
        $this->addItem($cart, $this->variant($this->latte), 1, [$this->milkOption('شیر معمولی')]);        // 850,000 (not coffee)
        $quote = $this->getJson('/api/v1/public/cart', $this->publicHeaders(['X-Cart-Token' => $cart]))->json('data.quote');

        $this->assertSame(130_000, $quote['discount']['amount']); // 20% of the espresso only
    }

    public function test_time_window_and_weekdays(): void
    {
        // Happy hour: Saturday–Wednesday, 15:00–18:00 Tehran time.
        $this->createDiscount(['name' => 'ساعت خوش', 'schedule' => ['weekdays' => [6, 7, 1, 2, 3], 'from' => '15:00', 'to' => '18:00']]);

        $this->travelTo(CarbonImmutable::parse('2026-09-26 16:00', 'Asia/Tehran')); // Saturday
        $this->assertSame('ساعت خوش', $this->quote()['discount']['name']);

        $this->travelTo(CarbonImmutable::parse('2026-09-26 19:00', 'Asia/Tehran'));
        $this->assertNull($this->quote()['discount']);

        $this->travelTo(CarbonImmutable::parse('2026-09-24 16:00', 'Asia/Tehran')); // Thursday
        $this->assertNull($this->quote()['discount']);
    }

    public function test_usage_limits_are_enforced_at_checkout(): void
    {
        $this->createDiscount(['code' => 'ONCE', 'usage_limit' => 1]);

        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso));
        $this->checkout($cart, ['coupon_code' => 'once'])->assertCreated()->assertJsonPath('data.discount.code', 'ONCE')->assertJsonPath('data.discount_total', 65_000);

        $this->assertSame(1, $this->inTenant($this->tenant, fn () => Discount::query()->where('code', 'ONCE')->value('used_count')));

        $cart2 = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart2, $this->variant($this->espresso));
        $this->checkout($cart2, ['coupon_code' => 'ONCE'])->assertUnprocessable()->assertJsonPath('code', 'coupon_exhausted');
    }

    public function test_per_customer_limit_needs_login(): void
    {
        $this->createDiscount(['code' => 'FIRST', 'per_customer_limit' => 1]);
        $this->assertSame('coupon_login_required', $this->quote('FIRST')['issues'][0]['code']);

        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $order = function () use ($auth) {
            $cart = $this->cart('takeaway', $auth);
            $this->addItem($cart, $this->variant($this->espresso), headers: $auth);

            return $this->checkout($cart, ['coupon_code' => 'FIRST'], $auth);
        };

        $order()->assertCreated();
        $order()->assertUnprocessable()->assertJsonPath('code', 'coupon_already_used');
    }

    public function test_used_discounts_are_deactivated_not_deleted(): void
    {
        $id = $this->createDiscount(['code' => 'KEEP']);
        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->espresso));
        $this->checkout($cart, ['coupon_code' => 'KEEP'])->assertCreated();

        $this->deleteJson("/api/v1/discounts/{$id}", [], $this->staffHeaders($this->owner, $this->tenant))->assertNoContent();
        $this->assertFalse($this->inTenant($this->tenant, fn () => Discount::query()->findOrFail($id)->is_active));

        $unused = $this->createDiscount(['code' => 'GONE']);
        $this->deleteJson("/api/v1/discounts/{$unused}", [], $this->staffHeaders($this->owner, $this->tenant))->assertNoContent();
        $this->assertNull($this->inTenant($this->tenant, fn () => Discount::query()->find($unused)));
    }
}
