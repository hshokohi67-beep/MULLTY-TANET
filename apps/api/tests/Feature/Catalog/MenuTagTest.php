<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use Tests\Feature\Commerce\CommerceTestCase;

/** The café's own diet/allergy tags, next to the platform's list. */
final class MenuTagTest extends CommerceTestCase
{
    public function test_a_cafe_adds_its_own_tag_uses_it_and_deletes_it(): void
    {
        $h = $this->staffHeaders($this->owner, $this->tenant);

        $tag = $this->postJson('/api/v1/catalog/menu-tags', ['label' => '  بدون   تخم‌مرغ '], $h)->assertCreated()->json('data');
        $this->assertSame('بدون تخم‌مرغ', $tag['label']);
        $this->assertSame('c:'.$tag['id'], $tag['key']);
        $this->postJson('/api/v1/catalog/menu-tags', ['label' => 'بدون تخم‌مرغ'], $h)->assertUnprocessable()->assertJsonValidationErrors('label');
        $this->postJson('/api/v1/catalog/menu-tags', ['label' => 'گیاهی'], $h)->assertUnprocessable()->assertJsonValidationErrors('label'); // already on the platform list

        $this->getJson('/api/v1/catalog/menu-tags', $h)->assertOk()
            ->assertJsonPath('data.custom.0.label', 'بدون تخم‌مرغ')
            ->assertJsonPath('data.platform.0.key', 'vegan');

        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}", ['name' => 'اسپرسو', 'dietary_tags' => ['vegan', $tag['key']]], $h)->assertOk();
        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}", ['name' => 'اسپرسو', 'dietary_tags' => ['c:01ARZ3NDEKTSV4RRFFQ69G5FAV']], $h)->assertUnprocessable();

        $menu = $this->getJson('/api/v1/public/menu?branch=main', $this->publicHeaders())->json('data.products');
        $this->assertSame([['key' => 'vegan', 'label' => 'گیاهی'], ['key' => $tag['key'], 'label' => 'بدون تخم‌مرغ']], collect($menu)->firstWhere('name', 'اسپرسو')['dietary_tags']);

        $this->deleteJson("/api/v1/catalog/menu-tags/{$tag['id']}", [], $h)->assertNoContent();
        $this->assertSame(['vegan'], $this->inTenant($this->tenant, fn () => Product::query()->findOrFail($this->espresso->id)->dietary_tags));
    }

    public function test_only_catalog_managers_change_tags(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->postJson('/api/v1/catalog/menu-tags', ['label' => 'تند ملایم'], $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
    }
}
