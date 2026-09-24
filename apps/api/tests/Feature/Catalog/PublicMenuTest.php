<?php

namespace Tests\Feature\Catalog;

final class PublicMenuTest extends CatalogTestCase
{
    public function test_hidden_inactive_and_sold_out_items(): void
    {
        $visible = $this->createProduct('لاته')->json('data.id');
        $soldOut = $this->createProduct('چیزکیک')->json('data.id');
        $hidden = $this->createProduct('فصلی')->json('data.id');
        $this->createProduct('غیرفعال', extra: ['is_active' => false]);
        $h = $this->headers();

        $this->putJson("/api/v1/catalog/products/{$soldOut}/availability", ['branch_id' => $this->main->id, 'status' => 'sold_out', 'sold_out_until' => now()->addHours(3)->toIso8601String()], $h)->assertOk();
        $this->putJson("/api/v1/catalog/products/{$hidden}/availability", ['branch_id' => $this->main->id, 'status' => 'hidden'], $h)->assertOk();

        $products = collect($this->publicMenu()->json('data.products'))->keyBy('id');

        $this->assertEqualsCanonicalizing([$visible, $soldOut], $products->keys()->all());
        $this->assertTrue($products[$visible]['is_available']);
        $this->assertFalse($products[$soldOut]['is_available']);

        // "Sold out until 3 hours from now" expires by itself.
        $this->travel(4)->hours();
        $this->assertTrue(collect($this->publicMenu()->json('data.products'))->keyBy('id')[$soldOut]['is_available']);
    }

    public function test_cache_is_invalidated_by_catalog_changes(): void
    {
        $id = $this->createProduct('لاته', [[null, 900_000]])->json('data');
        $this->publicMenu()->assertJsonPath('data.products.0.name', 'لاته');

        $this->putJson("/api/v1/catalog/products/{$id['id']}", ['name' => 'لاته مخصوص'], $this->headers())->assertOk();

        $this->publicMenu()->assertJsonPath('data.products.0.name', 'لاته مخصوص');
    }

    public function test_public_projection_is_an_allow_list(): void
    {
        $this->createProduct('لاته', [[null, 900_000]], ['description' => 'خوشمزه']);

        $product = $this->publicMenu()->json('data.products.0');

        $this->assertEqualsCanonicalizing(
            ['id', 'slug', 'name', 'description', 'is_featured', 'temperature', 'is_available', 'category_ids', 'price_from', 'variants', 'images', 'nutrition', 'dietary_tags', 'modifier_groups'],
            array_keys($product),
        );
        $this->assertArrayNotHasKey('sku', $product['variants'][0]);
        $this->getJson('/api/v1/public/menu?branch=main', ['X-Tenant' => 'nope'])->assertNotFound();
        $this->getJson('/api/v1/public/menu?branch=nope', ['X-Tenant' => $this->tenant->slug])->assertNotFound();
    }

    public function test_menus_of_two_tenants_never_mix(): void
    {
        $this->createProduct('لاته الف');
        ['tenant' => $b, 'owner' => $ownerB] = $this->createTenantWithOwner('cafe-b');
        $this->postJson('/api/v1/catalog/products', ['name' => 'لاته ب', 'variants' => [['base_price' => 1]]], $this->staffHeaders($ownerB, $b))->assertCreated();

        $this->assertSame(['لاته الف'], collect($this->publicMenu()->json('data.products'))->pluck('name')->all());
        $this->assertSame(['لاته ب'], collect($this->getJson('/api/v1/public/menu', ['X-Tenant' => 'cafe-b'])->json('data.products'))->pluck('name')->all());
    }
}
