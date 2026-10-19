<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Loyalty\Models\LoyaltyTier;
use Tests\Feature\Commerce\CommerceTestCase;

/** «مجموعه‌ی منو», the richer product details and the public club page. */
final class MenuCollectionTest extends CommerceTestCase
{
    /** @return array<string, mixed> */
    private function menu(): array
    {
        return $this->getJson('/api/v1/public/menu?branch=main', $this->publicHeaders())->assertOk()->json('data');
    }

    public function test_a_collection_reaches_the_menu_with_its_window_and_theme(): void
    {
        $h = $this->staffHeaders($this->owner, $this->tenant);
        $id = $this->postJson('/api/v1/catalog/collections', [
            'title' => 'طعم‌های پاییزی', 'subtitle' => 'دارچین و کدو حلوایی', 'theme' => 'autumn',
            'starts_at' => now()->subDay()->toIso8601String(), 'ends_at' => now()->addDays(10)->toIso8601String(), 'occasion_key' => 'autumn_start',
            'product_ids' => [$this->latte->id, $this->espresso->id],
        ], $h)->assertCreated()->json('data.id');

        $collection = $this->menu()['collections'][0];
        $this->assertSame('طعم‌های پاییزی', $collection['title']);
        $this->assertSame('autumn', $collection['theme']);
        $this->assertSame([$this->latte->id, $this->espresso->id], $collection['product_ids']);
        $this->assertNotNull($collection['window']['to']);

        // Ended collections leave the menu by themselves.
        $this->travel(11)->days();
        $this->assertSame([], $this->menu()['collections']);

        // Only the café's own products.
        ['tenant' => $other, 'owner' => $otherOwner] = $this->createTenantWithOwner('cafe-b');
        $this->putJson("/api/v1/catalog/collections/{$id}", ['title' => 'x', 'product_ids' => [$this->latte->id]], $this->staffHeaders($otherOwner, $other))->assertNotFound();
        $this->postJson('/api/v1/catalog/collections', ['title' => 'x', 'product_ids' => [$this->latte->id]], $this->staffHeaders($otherOwner, $other))->assertUnprocessable();

        $this->deleteJson("/api/v1/catalog/collections/{$id}", [], $h)->assertNoContent();
    }

    public function test_product_details_allergens_caffeine_and_new(): void
    {
        $h = $this->staffHeaders($this->owner, $this->tenant);
        $this->putJson("/api/v1/catalog/products/{$this->latte->id}", [
            'name' => 'لاته', 'name_en' => 'Latte', 'ingredients' => 'اسپرسو، شیر گرم', 'allergens' => ['dairy', 'caffeine'],
            'caffeine_source' => 'coffee', 'caffeine_dose' => '۲ شات', 'is_new' => true,
        ], $h)->assertOk()->assertJsonPath('data.allergens', ['dairy', 'caffeine']);
        $this->putJson("/api/v1/catalog/products/{$this->latte->id}", ['name' => 'لاته', 'allergens' => ['plutonium']], $h)->assertUnprocessable();

        $latte = collect($this->menu()['products'])->firstWhere('name', 'لاته');
        $this->assertSame('Latte', $latte['name_en']);
        $this->assertSame('اسپرسو، شیر گرم', $latte['ingredients']);
        $this->assertSame([['key' => 'dairy', 'label' => 'لبنیات و لاکتوز'], ['key' => 'caffeine', 'label' => 'کافئین']], $latte['allergens']);
        $this->assertSame(['source' => 'قهوه', 'dose' => '۲ شات'], $latte['caffeine']);
        $this->assertTrue($latte['is_new']);

        // Automatic «جدید»: added after the café's first week, for three weeks.
        $espresso = collect($this->menu()['products'])->firstWhere('name', 'اسپرسو');
        $this->assertFalse($espresso['is_new']); // part of the initial menu
        $this->travel(10)->days();
        $this->inTenant($this->tenant, fn () => Product::query()->whereKey($this->espresso->id)->update(['created_at' => now()]));
        $this->travel(1)->minutes();
        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}", ['name' => 'اسپرسو'], $h)->assertOk(); // bumps the menu cache
        $this->assertTrue(collect($this->menu()['products'])->firstWhere('name', 'اسپرسو')['is_new']);
    }

    public function test_public_club_page_shows_only_program_facts(): void
    {
        $this->getJson('/api/v1/public/club', $this->publicHeaders())->assertNotFound(); // club off

        $h = $this->staffHeaders($this->owner, $this->tenant);
        $this->patchJson('/api/v1/tenant/settings', ['settings' => ['loyalty.enabled' => true, 'loyalty.birthday_points' => 50]], $h)->assertOk();
        $this->inTenant($this->tenant, fn () => LoyaltyTier::query()->create(['name' => 'طلایی', 'min_spend' => 50_000_000, 'color' => '#c9a227', 'points_multiplier' => 15000, 'sort' => 1]));

        $data = $this->getJson('/api/v1/public/club', $this->publicHeaders())->assertOk()->json('data');
        $this->assertSame('طلایی', $data['tiers'][0]['name']);
        $this->assertSame('#c9a227', $data['tiers'][0]['color']);
        $this->assertSame(50, $data['birthday_points']);
        $this->assertSame(['points_per_100k', 'point_value', 'min_redeem_points', 'birthday_wallet_gift', 'birthday_points', 'referral_referrer_reward', 'referral_referee_reward', 'wallet_payments', 'tiers', 'cashback'], array_keys($data));
    }
}
