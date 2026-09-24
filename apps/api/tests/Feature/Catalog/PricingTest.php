<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\PriceChangeLog;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Branch;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantUser;

final class PricingTest extends CatalogTestCase
{
    public function test_branch_override_wins_and_can_be_removed(): void
    {
        $vanak = $this->inTenant($this->tenant, fn () => Branch::query()->create(['name' => 'ونک', 'slug' => 'vanak']));
        $product = $this->createProduct('لاته', [[null, 1_000_000]])->json('data');
        $variantId = $product['variants'][0]['id'];

        $this->putJson("/api/v1/catalog/products/{$product['id']}/branch-prices", [
            'branch_id' => $vanak->id,
            'prices' => [['variant_id' => $variantId, 'amount' => 1_200_000]],
        ], $this->headers())->assertOk()->assertJsonPath('data.variants.0.branch_prices.0.amount', 1_200_000);

        $this->publicMenu('vanak')->assertJsonPath('data.products.0.price_from', 1_200_000);
        $this->publicMenu('main')->assertJsonPath('data.products.0.price_from', 1_000_000);

        $this->putJson("/api/v1/catalog/products/{$product['id']}/branch-prices", [
            'branch_id' => $vanak->id,
            'prices' => [['variant_id' => $variantId, 'amount' => null]],
        ], $this->headers())->assertOk()->assertJsonCount(0, 'data.variants.0.branch_prices');

        $this->publicMenu('vanak')->assertJsonPath('data.products.0.price_from', 1_000_000);
    }

    public function test_every_price_change_is_in_history(): void
    {
        $product = $this->createProduct('اسپرسو', [[null, 600_000]])->json('data');
        $variant = $product['variants'][0]['id'];

        $this->putJson("/api/v1/catalog/products/{$product['id']}/variants", ['variants' => [['id' => $variant, 'base_price' => 650_000]]], $this->headers())->assertOk();

        $history = $this->getJson('/api/v1/catalog/price-history?variant_id='.$variant, $this->headers())->assertOk()->json('data');

        $this->assertSame([[600_000, 650_000, 'manual'], [null, 600_000, 'manual']], array_map(fn ($r) => [$r['old_amount'], $r['new_amount'], $r['reason']], $history));
        $this->assertSame($this->owner->id, $history[0]['actor_id']);
    }

    public function test_bulk_preview_changes_nothing_and_apply_is_batched_and_audited(): void
    {
        $h = $this->headers();
        $hot = $this->postJson('/api/v1/catalog/categories', ['name' => 'گرم'], $h)->json('data.id');
        $this->createProduct('لاته', [['کوچک', 850_000], ['بزرگ', 1_050_000]], ['category_ids' => [$hot]]);
        $this->createProduct('کیک', [[null, 1_300_000]]);

        $payload = ['target' => ['category_ids' => [$hot]], 'operation' => 'percent_increase', 'value' => 700, 'round_to' => 10_000];

        $preview = $this->postJson('/api/v1/catalog/prices/bulk', [...$payload, 'preview' => true], $h)->assertOk()->json('data');
        $this->assertSame(2, $preview['changed_count']);
        $this->assertNull($preview['batch_id']);
        $this->assertSame([910_000, 1_120_000], array_column($preview['rows'], 'new_amount')); // 909,500→910,000 · 1,123,500→1,120,000
        $this->assertSame(850_000, collect($this->publicMenu()->json('data.products'))->firstWhere('name', 'لاته')['price_from']); // unchanged

        $applied = $this->postJson('/api/v1/catalog/prices/bulk', $payload, $h)
            ->assertOk()->assertJsonPath('message', 'قیمت 2 مورد به‌روز شد.')->json('data');

        $this->assertSame(2, $this->inTenant($this->tenant, fn () => PriceChangeLog::query()->where('batch_id', $applied['batch_id'])->where('reason', 'bulk')->count()));
        $this->assertTrue($this->inTenant($this->tenant, fn () => AuditLog::query()->where('action', 'prices.bulk_updated')->exists()));

        // The cake (not in the category) kept its price.
        $menu = collect($this->publicMenu()->json('data.products'))->keyBy('name');
        $this->assertSame(1_300_000, $menu['کیک']['price_from']);
        $this->assertSame(910_000, $menu['لاته']['price_from']);
    }

    public function test_bulk_refuses_negative_prices_atomically(): void
    {
        $this->createProduct('ارزان', [[null, 100_000]]);
        $this->createProduct('گران', [[null, 900_000]]);

        $this->postJson('/api/v1/catalog/prices/bulk', ['target' => ['all' => true], 'operation' => 'fixed_decrease', 'value' => 200_000], $this->headers())
            ->assertUnprocessable()->assertJsonPath('code', 'price_negative');

        // Nothing changed, not even the expensive item processed first/last.
        $this->assertSame([100_000, 900_000], collect($this->publicMenu()->json('data.products'))->pluck('price_from')->sort()->values()->all());
    }

    public function test_bulk_ignores_deleted_products_and_requires_a_target(): void
    {
        $id = $this->createProduct('حذفی', [[null, 500_000]])->json('data.id');
        $this->deleteJson("/api/v1/catalog/products/{$id}", [], $this->headers());

        $this->postJson('/api/v1/catalog/prices/bulk', ['target' => ['all' => true], 'operation' => 'percent_increase', 'value' => 1000], $this->headers())
            ->assertOk()->assertJsonPath('data.changed_count', 0);

        $this->postJson('/api/v1/catalog/prices/bulk', ['target' => [], 'operation' => 'percent_increase', 'value' => 1000], $this->headers())
            ->assertUnprocessable()->assertJsonPath('errors.target.0', 'مشخص کنید تغییر قیمت روی کدام محصولات یا دسته‌بندی‌ها اعمال شود.');
    }

    public function test_changing_prices_through_product_update_needs_the_price_permission(): void
    {
        $product = $this->createProduct('لاته', [[null, 900_000]])->json('data');

        // A role that may edit the menu but not prices.
        $editor = $this->addMember($this->tenant, $this->owner, 'waiter');
        $this->inTenant($this->tenant, function () use ($editor) {
            $role = Role::query()->create(['key' => 'menu-editor', 'name' => 'ویرایشگر منو']);
            $role->permissions()->sync(Permission::query()->whereIn('key', ['catalog.view', 'catalog.manage'])->pluck('id'));
            TenantUser::query()->where('user_id', $editor->id)->firstOrFail()->roles()->attach($role->id, ['tenant_id' => $role->tenant_id]);
        });

        $this->putJson("/api/v1/catalog/products/{$product['id']}", ['name' => 'لاته ویژه'], $this->headers($editor))->assertOk();
        $this->putJson("/api/v1/catalog/products/{$product['id']}", [
            'name' => 'لاته ویژه',
            'variants' => [['id' => $product['variants'][0]['id'], 'base_price' => 1]],
        ], $this->headers($editor))->assertForbidden();

        $this->publicMenu()->assertJsonPath('data.products.0.price_from', 900_000);
    }
}
