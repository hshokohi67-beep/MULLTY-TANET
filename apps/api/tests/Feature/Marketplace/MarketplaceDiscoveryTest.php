<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Models\MarketplaceListing;
use Carbon\CarbonImmutable;
use Tests\Feature\Commerce\CommerceTestCase;

/** Locations, offers, quick filters, sorts, collections and type-ahead on top of the Phase 13 basics. */
final class MarketplaceDiscoveryTest extends CommerceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00', 'Asia/Tehran'));
        $this->inTenant($this->tenant, function (): void {
            $this->branch->update(['city' => 'تهران', 'province' => 'تهران', 'district' => 'ونک']);
            MarketplaceListing::query()->create(['is_listed' => true, 'categories' => ['cafe'], 'amenities' => ['workspace', 'wifi'], 'price_level' => 1, 'listed_at' => now()]);
        });
    }

    private function project(): void
    {
        $this->inTenant($this->tenant, fn () => app(ProjectStore::class)->handle());
    }

    /** @return list<string> */
    private function slugs(string $query): array
    {
        return array_column($this->getJson('/api/v1/public/marketplace/stores?'.$query, ['Accept' => 'application/json'])->assertOk()->json('data'), 'store');
    }

    public function test_only_public_automatic_offers_are_shown(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Discount::query()->create(['name' => 'قهوه‌ی صبح', 'kind' => 'percent', 'value' => 2000, 'applies_to' => 'order', 'min_order' => 1_000_000, 'is_active' => true, 'priority' => 1]);
            Discount::query()->create(['name' => 'کد مخفی', 'code' => 'SECRET50', 'kind' => 'percent', 'value' => 5000, 'applies_to' => 'order', 'min_order' => 0, 'is_active' => true, 'priority' => 2]);
            $members = Discount::query()->create(['name' => 'فقط طلایی', 'kind' => 'fixed', 'value' => 100_000, 'applies_to' => 'order', 'min_order' => 0, 'is_active' => true, 'priority' => 3]);
            DiscountRule::query()->create(['discount_id' => $members->id, 'rule_type' => 'tier', 'target' => 'gold']);
            Discount::query()->create(['name' => 'تمام‌شده', 'kind' => 'percent', 'value' => 1000, 'applies_to' => 'order', 'min_order' => 0, 'is_active' => true, 'priority' => 4, 'ends_at' => now()->subDay()]);
        });
        $this->project();

        $card = $this->getJson('/api/v1/public/marketplace/stores', ['Accept' => 'application/json'])->json('data.0');
        $this->assertSame('٪۲۰ تخفیف برای خرید بالای ۱۰۰٬۰۰۰ تومان', $card['offer']);
        $profile = $this->getJson('/api/v1/public/marketplace/stores/cafe-a', ['Accept' => 'application/json'])->json('data');
        $this->assertSame(['٪۲۰ تخفیف برای خرید بالای ۱۰۰٬۰۰۰ تومان'], $profile['offers']);
        $raw = (string) $this->getJson('/api/v1/public/marketplace/stores/cafe-a', ['Accept' => 'application/json'])->getContent();
        foreach (['SECRET50', 'طلایی', 'تمام‌شده', 'used_count', 'usage_limit'] as $private) {
            $this->assertStringNotContainsString($private, $raw);
        }
        $this->assertSame(['cafe-a'], $this->slugs('offers=1'));
    }

    public function test_places_filters_sorts_collections_and_suggest(): void
    {
        // A second branch in another district, open late, with delivery and a vegan item.
        $this->inTenant($this->tenant, function (): void {
            $second = Branch::query()->create(['name' => 'شعبه تجریش', 'slug' => 'tajrish', 'city' => 'تهران', 'province' => 'تهران', 'district' => 'تجریش', 'latitude' => 35.80, 'longitude' => 51.43]);
            BranchOpeningHour::query()->create(['branch_id' => $second->id, 'weekday' => 6, 'opens_at' => '09:00', 'closes_at' => '01:00']);
            Product::query()->whereKey($this->latte->id)->update(['dietary_tags' => json_encode(['vegan'])]);
        });
        $this->project();

        $home = $this->getJson('/api/v1/public/marketplace/home', ['Accept' => 'application/json'])->json('data');
        $this->assertSame('تهران', $home['places'][0]['province']);
        $this->assertEqualsCanonicalizing(['ونک', 'تجریش'], array_column($home['places'][0]['cities'][0]['districts'], 'district'));
        $this->assertSame([], $home['collections']); // a collection needs at least two cafés; this marketplace has one

        $branches = fn (string $q) => array_column($this->getJson('/api/v1/public/marketplace/stores?'.$q, ['Accept' => 'application/json'])->json('data'), 'branch');
        $this->assertSame(['tajrish'], $branches('district='.urlencode('تجریش')));
        $this->assertSame(['tajrish'], $branches('late=1'));
        $this->assertSame(['main'], $branches('delivery=1'));   // the delivery zone belongs to the main branch
        $this->assertSame(['main'], $branches('free_delivery=1'));
        $this->assertCount(2, $branches('dietary[]=vegan'));
        $this->assertSame([], $branches('dietary[]=gluten_free'));
        $this->assertCount(2, $branches('stores[]=cafe-a&stores[]=nope'));
        $this->assertCount(2, $branches('new=1&price=1&sort=price_asc'));
        $this->getJson('/api/v1/public/marketplace/stores?stores[]='.urlencode('<script>'), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->getJson('/api/v1/public/marketplace/stores?sort=cheapest', ['Accept' => 'application/json'])->assertUnprocessable();

        $s = $this->getJson('/api/v1/public/marketplace/suggest?q='.urlencode('تجر'), ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame([['city' => 'تهران', 'district' => 'تجریش', 'label' => 'تجریش، تهران']], $s['places']);
        $this->assertSame('cafe-a', $this->getJson('/api/v1/public/marketplace/suggest?q='.urlencode('کافه'), ['Accept' => 'application/json'])->json('data.stores.0.store'));
        $this->assertSame('اسپرسو', $this->getJson('/api/v1/public/marketplace/suggest?q='.urlencode('اسپر'), ['Accept' => 'application/json'])->json('data.dishes.0.name'));
        $this->getJson('/api/v1/public/marketplace/suggest?q='.str_repeat('x', 41), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertStringNotContainsString($this->tenant->id, (string) $this->getJson('/api/v1/public/marketplace/suggest?q='.urlencode('کافه'), ['Accept' => 'application/json'])->getContent());
    }
}
