<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class CatalogTest extends CatalogTestCase
{
    public function test_nested_categories_with_depth_limit_and_cycle_guard(): void
    {
        $h = $this->headers();
        $drinks = $this->postJson('/api/v1/catalog/categories', ['name' => 'نوشیدنی‌ها'], $h)->assertCreated()->assertJsonPath('data.slug', 'نوشیدنی-ها')->json('data.id'); // ZWNJ becomes a hyphen in URLs
        $hot = $this->postJson('/api/v1/catalog/categories', ['name' => 'گرم', 'parent_id' => $drinks], $h)->assertCreated()->json('data.id');
        $coffee = $this->postJson('/api/v1/catalog/categories', ['name' => 'قهوه', 'parent_id' => $hot], $h)->assertCreated()->json('data.id');

        $this->postJson('/api/v1/catalog/categories', ['name' => 'خیلی عمیق', 'parent_id' => $coffee], $h)
            ->assertUnprocessable()->assertJsonPath('code', 'category_too_deep');

        $this->putJson("/api/v1/catalog/categories/{$drinks}", ['name' => 'نوشیدنی‌ها', 'parent_id' => $coffee], $h)
            ->assertUnprocessable()->assertJsonPath('code', 'category_cycle');
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $h = $this->headers();
        $cat = $this->postJson('/api/v1/catalog/categories', ['name' => 'کیک'], $h)->json('data.id');
        $this->createProduct('چیزکیک', extra: ['category_ids' => [$cat]]);

        $this->deleteJson("/api/v1/catalog/categories/{$cat}", [], $h)->assertUnprocessable()->assertJsonPath('code', 'category_not_empty');
    }

    public function test_create_product_with_sizes_and_persian_slug(): void
    {
        $response = $this->createProduct('لاته وانیلی', [['کوچک', 850_000], ['بزرگ', 1_050_000]], [
            'nutrition' => ['calories' => 190],
            'dietary_tags' => ['vegetarian'],
        ]);

        $response->assertJsonPath('data.slug', 'لاته-وانیلی')
            ->assertJsonPath('data.variants.0.name', 'کوچک')
            ->assertJsonPath('data.variants.1.base_price', 1_050_000)
            ->assertJsonPath('data.price_from', 850_000)
            ->assertJsonPath('data.nutrition.calories', 190);

        // Same name again → unique slug.
        $this->createProduct('لاته وانیلی')->assertJsonPath('data.slug', 'لاته-وانیلی-2');
    }

    public function test_multiple_sizes_need_names_and_validation_is_persian(): void
    {
        $this->postJson('/api/v1/catalog/products', [
            'name' => 'لاته',
            'variants' => [['name' => '', 'base_price' => 1], ['name' => 'بزرگ', 'base_price' => -5]],
            'dietary_tags' => ['made_up'],
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variants.0.name' => 'وقتی محصول چند سایز/نوع دارد، هرکدام باید نام داشته باشد (مثلاً «کوچک» و «بزرگ»).',
                'variants.1.base_price' => 'قیمت باید دست‌کم 0 باشد.',
                'dietary_tags.0',
            ]);
    }

    public function test_quick_add_puts_an_item_on_the_menu_in_one_step(): void
    {
        $cat = $this->postJson('/api/v1/catalog/categories', ['name' => 'قهوه'], $this->headers())->json('data.id');

        $this->postJson('/api/v1/catalog/products/quick', ['name' => 'اسپرسو', 'price' => 650_000, 'category_id' => $cat], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.variants.0.name', null)
            ->assertJsonPath('data.variants.0.base_price', 650_000)
            ->assertJsonPath('data.categories.0.id', $cat);

        $this->publicMenu()->assertJsonPath('data.products.0.name', 'اسپرسو');
    }

    public function test_search_normalizes_arabic_letters_digits_and_zwnj(): void
    {
        $this->createProduct('کیک شکلاتی');
        $this->createProduct('چای ماسالا');
        $this->createProduct("قهوه\u{200C}ی ۲ شات");

        $names = fn (string $q) => collect($this->getJson('/api/v1/catalog/products?search='.urlencode($q), $this->headers())->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['کیک شکلاتی'], $names('كيك'));        // Arabic ك/ي
        $this->assertSame(['چای ماسالا'], $names('چاي'));
        $this->assertSame(["قهوه\u{200C}ی ۲ شات"], $names('قهوه ی 2'));
    }

    public function test_variants_sync_updates_keeps_and_removes(): void
    {
        $product = $this->createProduct('کاپوچینو', [['کوچک', 800_000], ['بزرگ', 1_000_000]])->json('data');
        [$small, $large] = $product['variants'];

        $this->putJson("/api/v1/catalog/products/{$product['id']}/variants", ['variants' => [
            ['id' => $small['id'], 'name' => 'کوچک', 'base_price' => 850_000],
            ['name' => 'متوسط', 'base_price' => 950_000],
        ]], $this->headers())
            ->assertOk()
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.variants.0.id', $small['id'])
            ->assertJsonPath('data.variants.0.base_price', 850_000)
            ->assertJsonPath('data.variants.1.name', 'متوسط');

        $this->assertNotContains($large['id'], collect($this->getJson("/api/v1/catalog/products/{$product['id']}", $this->headers())->json('data.variants'))->pluck('id'));
    }

    public function test_deleted_products_disappear_but_are_soft_deleted(): void
    {
        $id = $this->createProduct('کیک هویج')->json('data.id');

        $this->deleteJson("/api/v1/catalog/products/{$id}", [], $this->headers())->assertNoContent();
        $this->getJson("/api/v1/catalog/products/{$id}", $this->headers())->assertNotFound();
        $this->assertNotNull($this->inTenant($this->tenant, fn () => Product::withTrashed()->find($id)?->deleted_at));
    }

    public function test_product_images_validated_and_limited(): void
    {
        Storage::fake('public');
        $id = $this->createProduct('لاته')->json('data.id');
        $h = $this->headers();

        $this->post("/api/v1/catalog/products/{$id}/images", ['image' => UploadedFile::fake()->image('a.jpg', 800, 800)], $h)
            ->assertCreated()
            ->assertJsonPath('data.images.0.width', 800)
            ->assertJsonPath('data.images.0.alt', 'لاته');

        $this->post("/api/v1/catalog/products/{$id}/images", ['image' => UploadedFile::fake()->createWithContent('x.svg', '<svg/>')], $h)
            ->assertUnprocessable();
        $this->post("/api/v1/catalog/products/{$id}/images", ['image' => UploadedFile::fake()->image('tiny.png', 50, 50)], $h)
            ->assertUnprocessable();

        $files = Storage::disk('public')->allFiles();
        $this->assertCount(1, $files);
        $this->assertStringStartsWith("t/{$this->tenant->refresh()->media_key}/products/", $files[0]);
        $this->assertStringNotContainsString($this->tenant->id, $files[0]); // no internal ids in public URLs
    }

    public function test_modifier_group_rules(): void
    {
        $h = $this->headers();

        $this->postJson('/api/v1/catalog/modifier-groups', ['name' => 'شیر', 'min_select' => 2, 'max_select' => 1, 'modifiers' => []], $h)
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_selection_range');

        $this->postJson('/api/v1/catalog/modifier-groups', ['name' => 'شیر', 'min_select' => 1, 'max_select' => 1, 'modifiers' => [
            ['name' => 'معمولی', 'price_delta' => 0, 'is_default' => true],
            ['name' => 'بادام', 'price_delta' => 250_000, 'is_default' => true],
        ]], $h)->assertUnprocessable()->assertJsonPath('code', 'too_many_defaults');

        $group = $this->postJson('/api/v1/catalog/modifier-groups', ['name' => 'شیر', 'min_select' => 1, 'max_select' => 1, 'modifiers' => [
            ['name' => 'معمولی', 'price_delta' => 0, 'is_default' => true],
            ['name' => 'بادام', 'price_delta' => 250_000],
        ]], $h)->assertCreated()->assertJsonPath('data.is_required', true)->json('data');

        $product = $this->createProduct('لاته')->json('data.id');
        $this->putJson("/api/v1/catalog/products/{$product}/modifier-groups", ['modifier_group_ids' => [$group['id']]], $h)
            ->assertOk()->assertJsonPath('data.modifier_groups.0.name', 'شیر');

        $this->publicMenu()->assertJsonPath('data.products.0.modifier_groups.0.modifiers.1.price_delta', 250_000);
    }

    public function test_cashier_can_mark_sold_out_but_not_edit_the_menu(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $id = $this->createProduct('چیزکیک')->json('data.id');

        $this->putJson("/api/v1/catalog/products/{$id}/availability", ['branch_id' => $this->main->id, 'status' => 'sold_out'], $this->headers($cashier))
            ->assertOk()->assertJsonPath('data.availability.0.status_label', 'تمام شد');

        $this->putJson("/api/v1/catalog/products/{$id}", ['name' => 'تغییر'], $this->headers($cashier))->assertForbidden();
        $this->postJson('/api/v1/catalog/prices/bulk', ['target' => ['all' => true], 'operation' => 'percent_increase', 'value' => 1000], $this->headers($cashier))->assertForbidden();
    }
}
