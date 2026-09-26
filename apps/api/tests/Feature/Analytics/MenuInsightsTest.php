<?php

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Actions\RollupDay;
use App\Modules\Analytics\Models\DailyMetric;
use App\Modules\Analytics\Models\ProductMetric;
use App\Modules\Analytics\Models\ProductPairMetric;
use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Payments\PaymentsTestCase;

/** Best sellers and "bought together" on the public menu, from the analytics aggregates only. */
final class MenuInsightsTest extends PaymentsTestCase
{
    private Product $cake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00', 'Asia/Tehran'));
        $this->cake = $this->inTenant($this->tenant, fn () => app(SaveProduct::class)->handle(new ProductData('کیک شکلاتی'), null, [new VariantData(null, null, 900_000)]));
    }

    /** @return array<string, mixed> */
    private function menu(): array
    {
        Cache::flush();

        return $this->getJson('/api/v1/public/menu?branch='.$this->branch->slug, $this->publicHeaders())->assertOk()->json('data');
    }

    private function sold(string $productId, int $quantity, string $date = '2026-09-20'): void
    {
        $this->inTenant($this->tenant, fn () => ProductMetric::query()->create([
            'branch_id' => $this->branch->id, 'business_date' => $date, 'product_id' => $productId, 'product_name' => 'x', 'quantity' => $quantity, 'revenue' => 0,
        ]));
    }

    public function test_rollup_counts_products_bought_in_the_same_order(): void
    {
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->espresso), 2, [], $auth)->assertCreated();
        $this->addItem($cart, $this->variant($this->cake->refresh()), 1, [], $auth)->assertCreated();
        $this->checkout($cart, [], $auth)->assertCreated();
        $this->quickQrOrder(); // espresso alone: no pair

        $this->inTenant($this->tenant, function (): void {
            // A second branch: every branch is rolled up in one pass (regression: no variable reuse).
            Branch::query()->create(['name' => 'شعبه دوم', 'slug' => 'second', 'is_active' => true]);
            app(RollupDay::class)->handle('2026-09-26');
            $this->assertSame(1, DailyMetric::query()->count());
            $pair = ProductPairMetric::query()->sole();
            $ids = [$this->espresso->id, $this->cake->id];
            sort($ids);
            $this->assertSame($ids, [$pair->product_a, $pair->product_b]);
            $this->assertSame(1, $pair->orders);
        });
    }

    public function test_best_sellers_and_pairs_reach_the_public_menu(): void
    {
        $this->assertSame(['popular' => [], 'pairs' => []], $this->menu()['insights']);

        $this->sold($this->latte->id, 30);
        $this->sold($this->espresso->id, 12);
        $this->sold($this->cake->id, 3);                       // too few to be a best seller
        $this->sold($this->cake->id, 90, '2026-08-01');        // outside the 30-day window
        $ids = [$this->espresso->id, $this->cake->id];
        sort($ids);
        foreach (['2026-09-10', '2026-09-20'] as $date) {
            $this->inTenant($this->tenant, fn () => ProductPairMetric::query()->create(['branch_id' => $this->branch->id, 'business_date' => $date, 'product_a' => $ids[0], 'product_b' => $ids[1], 'orders' => 2]));
        }

        $insights = $this->menu()['insights'];
        $this->assertSame([$this->latte->id, $this->espresso->id], $insights['popular']);
        $this->assertSame([$this->cake->id], $insights['pairs'][$this->espresso->id]);
        $this->assertSame([$this->espresso->id], $insights['pairs'][$this->cake->id]);

        // A product switched off leaves the insights with the menu; the café can hide both.
        $this->inTenant($this->tenant, fn () => Product::query()->whereKey($this->cake->id)->update(['is_active' => false]));
        $this->assertArrayNotHasKey($this->espresso->id, $this->menu()['insights']['pairs']);

        $this->patchJson('/api/v1/tenant/settings', ['settings' => ['storefront.show_popular' => false, 'storefront.suggestions' => false]], $this->staffHeaders($this->owner, $this->tenant))->assertOk();
        $this->assertSame(['popular' => [], 'pairs' => []], $this->menu()['insights']);
    }

    public function test_menu_settings_are_published_with_the_storefront_and_validated(): void
    {
        $this->getJson('/api/v1/public/storefront', $this->publicHeaders())->assertOk()
            ->assertJsonPath('data.features.menu', ['look' => 'bright', 'layout' => 'list', 'categories' => 'top', 'show_calories' => true]);

        $h = $this->staffHeaders($this->owner, $this->tenant);
        $this->patchJson('/api/v1/tenant/settings', ['settings' => ['storefront.menu_look' => 'neon']], $h)->assertUnprocessable();
        $this->patchJson('/api/v1/tenant/settings', ['settings' => ['storefront.menu_look' => 'landing', 'storefront.menu_layout' => 'grid', 'storefront.menu_categories' => 'side', 'storefront.show_calories' => false]], $h)->assertOk();

        Cache::flush();
        $this->getJson('/api/v1/public/storefront', $this->publicHeaders())->assertOk()
            ->assertJsonPath('data.features.menu', ['look' => 'landing', 'layout' => 'grid', 'categories' => 'side', 'show_calories' => false]);
    }
}
