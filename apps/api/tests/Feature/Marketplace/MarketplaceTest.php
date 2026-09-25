<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Billing\Models\Addon;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionAddon;
use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Models\MarketplaceListing;
use Carbon\CarbonImmutable;
use Tests\Feature\Commerce\CommerceTestCase;

/**
 * cafe-a (the base café, Tehran) plus extra cafés created per test. Saturday 2026-09-26, 10:00 Tehran.
 */
final class MarketplaceTest extends CommerceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00', 'Asia/Tehran'));
        $this->inTenant($this->tenant, fn () => $this->branch->update(['city' => 'تهران', 'province' => 'تهران', 'address' => 'خیابان ولیعصر', 'phone' => '02188776655']));
    }

    /** @return array<string, string> */
    private function h(?User $user = null): array
    {
        return $this->staffHeaders($user ?? $this->owner, $this->tenant);
    }

    /** @return array<string, string> */
    private function pub(): array
    {
        return ['Accept' => 'application/json'];
    }

    /** @param  array<string, mixed>  $overrides */
    private function list(array $overrides = []): void
    {
        $this->putJson('/api/v1/marketplace/listing', [
            'is_listed' => true, 'headline' => 'قهوه‌ی دمی و کیک خانگی', 'about' => 'یک کافه‌ی دنج نزدیک پارک.',
            'categories' => ['cafe', 'specialty_coffee'], 'amenities' => ['wifi', 'workspace'], 'price_level' => 2, ...$overrides,
        ], $this->h())->assertOk();
    }

    /**
     * Another listed café, set up directly.
     *
     * @param  list<string>  $categories
     * @param  list<string>  $amenities
     */
    private function otherCafe(string $slug, string $city, array $categories, array $amenities = [], ?float $lat = null, ?float $lng = null): Tenant
    {
        ['tenant' => $t] = $this->createTenantWithOwner($slug);
        $this->inTenant($t, function () use ($city, $categories, $amenities, $lat, $lng): void {
            Branch::query()->firstOrFail()->update(['city' => $city, 'latitude' => $lat, 'longitude' => $lng]);
            app(SaveProduct::class)->handle(new ProductData('نان سنگک'), null, [new VariantData(null, null, 200_000)]);
            MarketplaceListing::query()->create(['is_listed' => true, 'categories' => $categories, 'amenities' => $amenities, 'price_level' => 1, 'listed_at' => now()]);
            app(ProjectStore::class)->handle();
        });

        return $t;
    }

    /** Walks a decoded response and fails on any key or value that should never be public. */
    private function assertNothingPrivate(string $json): void
    {
        foreach ([$this->tenant->id, $this->owner->phone_e164, $this->branch->id, 'search_text', 'popularity', '"tenant_id"', '"id"', 'email', 'password', 'merchant', 'subscription', 'settings'] as $needle) {
            $this->assertStringNotContainsString((string) $needle, $json, "public marketplace response leaked {$needle}");
        }
    }

    public function test_a_listed_cafe_appears_with_public_facts_only(): void
    {
        $status = $this->getJson('/api/v1/marketplace/listing', $this->h())->assertOk()->json('data');
        $this->assertFalse($status['eligible']);
        $this->assertFalse(collect($status['checks'])->firstWhere('key', 'listed')['ok']);
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertOk()->assertJsonPath('data.total', 0);

        $this->list();
        $status = $this->getJson('/api/v1/marketplace/listing', $this->h())->json('data');
        $this->assertTrue($status['eligible']);
        $this->assertSame(['cafe-a', 'تهران', true], [$status['preview']['store'], $status['preview']['city'], $status['preview']['is_open']]);
        $this->assertSame('/explore/cafe-a', $status['public_path']);

        $home = $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertOk()->assertHeader('Cache-Control');
        $this->assertSame(1, $home->json('data.total'));
        $this->assertSame([['city' => 'تهران', 'count' => 1]], $home->json('data.cities'));
        $this->assertSame(['cafe', 'specialty_coffee'], array_column($home->json('data.categories'), 'key'));

        // Search: menu items, Arabic letters and missing ZWNJ all find it.
        $this->assertSame(['cafe-a'], array_column($this->getJson('/api/v1/public/marketplace/stores?q='.urlencode('اسپرسو'), $this->pub())->json('data'), 'store'));
        $this->assertSame(['cafe-a'], array_column($this->getJson('/api/v1/public/marketplace/stores?q='.urlencode('قهوه تخصصي'), $this->pub())->json('data'), 'store'));
        $this->assertSame([], $this->getJson('/api/v1/public/marketplace/stores?q='.urlencode('پیتزا'), $this->pub())->json('data'));

        $profile = $this->getJson('/api/v1/public/marketplace/stores/cafe-a', $this->pub())->assertOk()->json('data');
        $this->assertSame(['/s/cafe-a', 'خیابان ولیعصر', '02188776655'], [$profile['storefront_path'], $profile['branches'][0]['address'], $profile['branches'][0]['phone']]);
        $this->assertContains('اسپرسو', array_column($profile['highlights'], 'name'));
        $this->assertSame(['dine_in' => true, 'takeaway' => true, 'delivery' => true, 'online_payment' => false, 'preorder' => true], $profile['services']);

        // A change through the panel reaches the marketplace straight away (projected when the request ends).
        $this->patchJson('/api/v1/tenant', ['name' => 'کافه آ'], $this->h())->assertOk();
        $this->getJson('/api/v1/public/marketplace/stores/cafe-a', $this->pub())->assertJsonPath('data.name', 'کافه آ');

        foreach (['/api/v1/public/marketplace/home', '/api/v1/public/marketplace/stores', '/api/v1/public/marketplace/stores/cafe-a'] as $url) {
            $this->assertNothingPrivate((string) $this->getJson($url, $this->pub())->getContent());
        }
    }

    public function test_hidden_unlisted_expired_or_incomplete_cafes_never_show(): void
    {
        $this->list();
        $admin = User::factory()->platformAdmin()->create();
        $ah = $this->staffHeaders($admin);

        // Platform moderation.
        $this->postJson("/api/v1/platform/marketplace/{$this->tenant->id}/hide", ['reason' => 'تصاویر نامناسب'], $ah)->assertOk()->assertJsonPath('data.eligible', false);
        $this->getJson('/api/v1/public/marketplace/stores/cafe-a', $this->pub())->assertNotFound();
        $this->assertStringContainsString('تصاویر نامناسب', collect($this->getJson('/api/v1/marketplace/listing', $this->h())->json('data.checks'))->firstWhere('key', 'moderation')['label']);
        $this->postJson("/api/v1/platform/marketplace/{$this->tenant->id}/unhide", [], $ah)->assertOk()->assertJsonPath('data.eligible', true);

        // The owner switches it off.
        $this->list(['is_listed' => false]);
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertJsonPath('data.total', 0);
        $this->list();

        // An empty menu (after the fact): the hourly refresh removes it.
        $this->inTenant($this->tenant, fn () => Product::query()->update(['is_active' => false]));
        $this->artisan('marketplace:refresh')->assertSuccessful();
        $this->getJson('/api/v1/public/marketplace/stores/cafe-a', $this->pub())->assertNotFound();
        $this->inTenant($this->tenant, fn () => Product::query()->update(['is_active' => true]));
        $this->artisan('marketplace:refresh')->assertSuccessful();
        $this->getJson('/api/v1/public/marketplace/stores/cafe-a', $this->pub())->assertOk();

        // The subscription runs out: gone from the marketplace, back once paid.
        $this->inTenant($this->tenant, fn () => Subscription::query()->firstOrFail()->update(['current_period_end' => now()->subDays(20)]));
        $this->artisan('marketplace:refresh')->assertSuccessful();
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertJsonPath('data.total', 0);
    }

    public function test_filters_nearest_open_now_pagination_and_featured(): void
    {
        $this->list();
        $this->otherCafe('nan-shiraz', 'شیراز', ['bakery']);
        $this->otherCafe('baghcheh', 'تهران', ['cafe'], ['outdoor'], 35.70, 51.40);

        $slugs = fn (string $q) => array_column($this->getJson('/api/v1/public/marketplace/stores?'.$q, $this->pub())->assertOk()->json('data'), 'store');
        $this->assertEqualsCanonicalizing(['cafe-a', 'baghcheh'], $slugs('city='.urlencode('تهران')));
        $this->assertSame(['nan-shiraz'], $slugs('category=bakery'));
        $this->assertSame(['baghcheh'], $slugs('amenities[]=outdoor'));
        $this->assertSame(['cafe-a'], $slugs('price=2'));
        // Nearest from a point next to baghcheh (cafe-a is ~7 km away; Shiraz has no coordinates).
        $near = $this->getJson('/api/v1/public/marketplace/stores?lat=35.701&lng=51.401', $this->pub())->json('data');
        $this->assertSame(['baghcheh', 'cafe-a', 'nan-shiraz'], array_column($near, 'store'));
        $this->assertLessThan(1, $near[0]['distance_km']);

        // Closed right now (opens at 16:00): filtered out by «open now».
        $this->inTenant($this->tenant, fn () => BranchOpeningHour::query()->create(['branch_id' => $this->branch->id, 'weekday' => 6, 'opens_at' => '16:00', 'closes_at' => '23:00']));
        $this->artisan('marketplace:refresh')->assertSuccessful(); // changed directly, not through a request
        $this->assertNotContains('cafe-a', $slugs('open_now=1'));
        $this->assertContains('cafe-a', $slugs(''));

        // Featured: by the platform (until a date) and by the add-on; always first and labelled.
        $shiraz = Tenant::query()->where('slug', 'nan-shiraz')->firstOrFail();
        $this->postJson("/api/v1/platform/marketplace/{$shiraz->id}/feature", ['until' => now()->addDays(7)->toIso8601String()], $this->staffHeaders(User::factory()->platformAdmin()->create()))->assertOk();
        $all = $this->getJson('/api/v1/public/marketplace/stores', $this->pub())->json('data');
        $this->assertSame(['nan-shiraz', true], [$all[0]['store'], $all[0]['is_featured']]);
        $this->inTenant($this->tenant, function (): void {
            SubscriptionAddon::query()->create(['subscription_id' => Subscription::query()->value('id'), 'addon_id' => Addon::query()->where('key', 'marketplace_featured')->value('id'), 'quantity' => 1]);
        });
        $this->artisan('marketplace:refresh')->assertSuccessful();
        $this->assertEqualsCanonicalizing(['nan-shiraz', 'cafe-a'], array_column($this->getJson('/api/v1/public/marketplace/home', $this->pub())->json('data.featured'), 'store'));

        // Three cafés now: the «work» collection (workspace amenity) needs two, «budget» (price 1) has two.
        $this->assertContains('budget', array_column($this->getJson('/api/v1/public/marketplace/home', $this->pub())->json('data.collections'), 'key'));

        $meta = $this->getJson('/api/v1/public/marketplace/stores?page=1', $this->pub())->json('meta');
        $this->assertSame(['page' => 1, 'per_page' => 24, 'total' => 3, 'last_page' => 1], $meta);
    }

    public function test_permissions_and_validation(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $manager = $this->addMember($this->tenant, $this->owner, 'manager');
        $this->getJson('/api/v1/marketplace/listing', $this->h($cashier))->assertForbidden();
        $this->getJson('/api/v1/marketplace/listing', $this->h($manager))->assertOk();

        $base = ['is_listed' => true, 'categories' => ['cafe'], 'amenities' => []];
        $this->putJson('/api/v1/marketplace/listing', [...$base, 'categories' => ['cafe', 'bakery', 'dessert', 'restaurant']], $this->h())->assertUnprocessable()->assertJsonValidationErrors('categories');
        $this->putJson('/api/v1/marketplace/listing', [...$base, 'categories' => ['casino']], $this->h())->assertUnprocessable()->assertJsonValidationErrors('categories.0');
        $this->putJson('/api/v1/marketplace/listing', [...$base, 'amenities' => ['wifi', 'wifi']], $this->h())->assertUnprocessable();
        $this->putJson('/api/v1/marketplace/listing', [...$base, 'price_level' => 9], $this->h())->assertUnprocessable()->assertJsonValidationErrors('price_level');
        $this->putJson('/api/v1/marketplace/listing', [...$base, 'headline' => str_repeat('ا', 121)], $this->h())->assertUnprocessable();

        $this->getJson('/api/v1/public/marketplace/stores?category=casino', $this->pub())->assertUnprocessable();
        $this->getJson('/api/v1/public/marketplace/stores?q='.str_repeat('x', 81), $this->pub())->assertUnprocessable();
        $this->getJson('/api/v1/public/marketplace/stores?q='.urlencode('%_\\'), $this->pub())->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/v1/public/marketplace/stores?lat=35', $this->pub())->assertUnprocessable();
        $this->getJson('/api/v1/public/marketplace/stores/'.urlencode('../etc'), $this->pub())->assertNotFound();

        $this->getJson('/api/v1/platform/marketplace', $this->h())->assertForbidden();
        $this->postJson("/api/v1/platform/marketplace/{$this->tenant->id}/hide", ['reason' => 'x'], $this->h())->assertForbidden();
    }
}
