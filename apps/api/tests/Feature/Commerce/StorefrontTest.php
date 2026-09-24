<?php

namespace Tests\Feature\Commerce;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Models\TenantSetting;
use Carbon\CarbonImmutable;

final class StorefrontTest extends CommerceTestCase
{
    public function test_storefront_shell_lists_active_branches_with_hours_and_features(): void
    {
        // Saturday 2026-09-26, 10:00 Tehran.
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00', 'Asia/Tehran'));

        $this->inTenant($this->tenant, function (): void {
            BranchOpeningHour::query()->create(['branch_id' => $this->branch->id, 'weekday' => 6, 'opens_at' => '12:00', 'closes_at' => '23:00']);
            Branch::query()->create(['name' => 'شعبه‌ی دوم', 'slug' => 'second', 'is_active' => true, 'sort' => 1]);
            Branch::query()->create(['name' => 'بسته', 'slug' => 'closed', 'is_active' => false, 'sort' => 2]);
            TenantSetting::query()->updateOrCreate(['key' => 'contact.phone'], ['value' => '02188888888']);
            TenantSetting::query()->updateOrCreate(['key' => 'loyalty.enabled'], ['value' => '1']);
        });

        $response = $this->getJson('/api/v1/public/storefront', $this->publicHeaders())->assertOk()
            ->assertJsonPath('data.slug', 'cafe-a')
            ->assertJsonPath('data.contact.phone', '02188888888')
            ->assertJsonPath('data.features.club', true)
            ->assertJsonPath('data.features.online_payment', false)
            ->assertJsonCount(2, 'data.branches')
            // Main: has hours (opens at 12:00 today) and a delivery zone with a location.
            ->assertJsonPath('data.branches.0.slug', 'main')
            ->assertJsonPath('data.branches.0.is_open', false)
            ->assertJsonPath('data.branches.0.next_opening_at', '2026-09-26T08:30:00+00:00')
            ->assertJsonPath('data.branches.0.opening_hours.0.opens_at', '12:00')
            ->assertJsonPath('data.branches.0.delivery', true)
            // Second: no schedule = always open; no zones = no delivery.
            ->assertJsonPath('data.branches.1.slug', 'second')
            ->assertJsonPath('data.branches.1.is_open', true)
            ->assertJsonPath('data.branches.1.delivery', false);

        // Explicit allow-list: nothing internal leaks.
        $json = $response->getContent();
        $this->assertStringNotContainsString('tenant_id', (string) $json);
        $this->assertStringNotContainsString('merchant', (string) $json);
    }

    public function test_delivery_check_for_a_map_pin(): void
    {
        // ~1.1 km north of the branch: inside the 3 km zone.
        $this->postJson('/api/v1/public/delivery/check', ['branch_id' => $this->branch->id, 'latitude' => 35.7675, 'longitude' => 51.4099], $this->publicHeaders())
            ->assertOk()
            ->assertJsonPath('data.zone_name', 'تا ۳ کیلومتر')
            ->assertJsonPath('data.fee', 300_000)
            ->assertJsonPath('data.min_order', 500_000)
            ->assertJsonPath('data.free_delivery_min', 3_000_000)
            ->assertJsonPath('data.eta_minutes', 40);

        // ~11 km away: outside.
        $this->postJson('/api/v1/public/delivery/check', ['branch_id' => $this->branch->id, 'latitude' => 35.8575, 'longitude' => 51.4099], $this->publicHeaders())
            ->assertStatus(422)->assertJsonPath('code', 'out_of_delivery_area');

        // A branch without zones.
        $other = $this->inTenant($this->tenant, fn () => Branch::query()->create(['name' => 'دوم', 'slug' => 'b2', 'is_active' => true, 'latitude' => 35.7, 'longitude' => 51.4]));
        $this->postJson('/api/v1/public/delivery/check', ['branch_id' => $other->id, 'latitude' => 35.7, 'longitude' => 51.4], $this->publicHeaders())
            ->assertStatus(422)->assertJsonPath('code', 'delivery_unavailable');

        $this->postJson('/api/v1/public/delivery/check', ['branch_id' => $this->branch->id], $this->publicHeaders())
            ->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_reorder_copies_lines_and_skips_what_is_no_longer_sold(): void
    {
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];

        $first = $this->cart('takeaway', $auth);
        $this->addItem($first, $this->variant($this->latte, 1), 2, [$this->milkOption('شیر بادام')], $auth)->assertCreated();
        $this->addItem($first, $this->variant($this->espresso), 1, [], $auth)->assertCreated();
        $orderId = $this->checkout($first, [], $auth)->assertCreated()->json('data.id');

        // History carries the tracking token for the customer's own orders.
        $this->getJson('/api/v1/customer/orders', $this->publicHeaders($auth))->assertOk()
            ->assertJsonPath('data.0.id', $orderId)
            ->assertJsonPath('data.0.tracking_token', fn ($t) => is_string($t) && strlen($t) > 20);

        // Espresso is switched off afterwards.
        $this->inTenant($this->tenant, fn () => Product::query()->whereKey($this->espresso->id)->update(['is_active' => false]));

        $cart = $this->cart('takeaway', $auth);
        $this->postJson('/api/v1/public/cart/reorder', ['order_id' => $orderId], $this->publicHeaders(['X-Cart-Token' => $cart, ...$auth]))
            ->assertOk()
            ->assertJsonPath('data.skipped', ['اسپرسو'])
            ->assertJsonCount(1, 'data.quote.lines')
            ->assertJsonPath('data.quote.lines.0.quantity', 2)
            ->assertJsonPath('data.quote.lines.0.modifiers.0.name', 'شیر بادام')
            ->assertJsonPath('data.quote.lines.0.line_total', 2 * 1_300_000);
    }

    public function test_reorder_needs_the_customer_who_placed_the_order(): void
    {
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->espresso), 1, [], $auth);
        $orderId = $this->checkout($cart, [], $auth)->assertCreated()->json('data.id');

        [, $otherToken] = $this->customer('+989122222222');
        $other = ['Authorization' => "Bearer {$otherToken}"];
        $otherCart = $this->cart('takeaway', $other);

        $this->postJson('/api/v1/public/cart/reorder', ['order_id' => $orderId], $this->publicHeaders(['X-Cart-Token' => $otherCart, ...$other]))
            ->assertNotFound();

        // Guests cannot reorder at all.
        $guestCart = $this->cart('takeaway');
        $this->postJson('/api/v1/public/cart/reorder', ['order_id' => $orderId], $this->publicHeaders(['X-Cart-Token' => $guestCart]))
            ->assertStatus(401)->assertJsonPath('code', 'login_required');
    }

    public function test_reorder_skips_a_deleted_variant(): void
    {
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->latte), 1, [$this->milkOption('شیر معمولی')], $auth);
        $orderId = $this->checkout($cart, [], $auth)->assertCreated()->json('data.id');

        $this->inTenant($this->tenant, fn () => ProductVariant::query()->where('product_id', $this->latte->id)->update(['is_active' => false]));

        $next = $this->cart('takeaway', $auth);
        $this->postJson('/api/v1/public/cart/reorder', ['order_id' => $orderId], $this->publicHeaders(['X-Cart-Token' => $next, ...$auth]))
            ->assertOk()->assertJsonPath('data.skipped', ['لاته'])->assertJsonCount(0, 'data.quote.lines');
    }
}
