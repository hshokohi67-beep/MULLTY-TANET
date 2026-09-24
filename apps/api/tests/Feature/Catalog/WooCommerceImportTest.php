<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\PriceChangeLog;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A miniature WordPress/WooCommerce database shaped like the legacy Live Cafe Menu store.
 */
final class WooCommerceImportTest extends CatalogTestCase
{
    private int $taxonomyId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy_wp' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy_wp');
        $this->buildWordPressSchema();
        $this->seedWordPress();
    }

    public function test_imports_categories_products_variants_upsells_and_is_idempotent(): void
    {
        $this->artisan('catalog:import-woocommerce', ['tenant' => 'cafe-a'])
            ->expectsOutputToContain('Import finished.')
            ->expectsOutputToContain('«بسته‌ی هدیه» از نوع grouped است')
            ->expectsOutputToContain('«آیس‌تی» حراج داشت')
            ->assertSuccessful();

        $this->inTenant($this->tenant, function () {
            $coffee = Category::query()->where('name', 'قهوه')->firstOrFail();
            $this->assertSame($coffee->id, Category::query()->where('name', 'لاته‌ها')->value('parent_id'));
            $this->assertFalse(Category::query()->where('slug', 'uncategorized')->exists());

            $espresso = Product::query()->where('name', 'اسپرسو')->with('variants.prices', 'modifierGroups.modifiers', 'categories')->firstOrFail();
            $this->assertSame(650_000, $espresso->variants->sole()->prices->sole()->amount); // 65,000 toman → rial
            $this->assertTrue($espresso->is_featured);
            $this->assertSame(['calories' => 5], $espresso->nutrition);
            $this->assertSame(['vegan'], $espresso->dietary_tags);
            $this->assertSame(['قهوه'], $espresso->categories->pluck('name')->all());
            $this->assertSame('افزودنی‌ها', $espresso->modifierGroups->sole()->name);
            $this->assertSame([['شکلات داغ', 400_000]], $espresso->modifierGroups->sole()->modifiers->map(fn ($m) => [$m->name, $m->price_delta])->all());

            $latte = Product::query()->where('name', 'لاته')->with('variants.prices')->firstOrFail();
            $this->assertSame(['کوچک', 'بزرگ'], $latte->variants->pluck('name')->all());
            $this->assertSame([850_000, 1_050_000], $latte->variants->map(fn ($v) => $v->prices->sole()->amount)->all());

            $this->assertSame(1_000_000, Product::query()->where('name', 'آیس‌تی')->firstOrFail()->variants()->with('prices')->first()->prices->sole()->amount); // regular, not sale
            $this->assertFalse(Product::query()->where('name', 'بسته‌ی هدیه')->exists());
            $this->assertFalse(Product::query()->where('name', 'بی‌قیمت')->exists());
            $this->assertTrue(PriceChangeLog::query()->where('reason', 'import')->exists());
        });

        // Change a price at the source and run again: updates in place, no duplicates.
        DB::connection('legacy_wp')->table('wp_postmeta')->where('post_id', 10)->where('meta_key', '_regular_price')->update(['meta_value' => '70000']);
        $this->artisan('catalog:import-woocommerce', ['tenant' => 'cafe-a'])->assertSuccessful();

        $this->inTenant($this->tenant, function () {
            $this->assertSame(1, Product::query()->where('name', 'اسپرسو')->count());
            $this->assertSame(2, Category::query()->count());
            $this->assertSame(1, ModifierGroup::query()->count());
            $this->assertSame(700_000, Product::query()->where('name', 'اسپرسو')->firstOrFail()->variants()->with('prices')->first()->prices->sole()->amount);
        });
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->artisan('catalog:import-woocommerce', ['tenant' => 'cafe-a', '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, $this->inTenant($this->tenant, fn () => Product::query()->count()));
    }

    public function test_unknown_tenant_fails(): void
    {
        $this->artisan('catalog:import-woocommerce', ['tenant' => 'nope'])->assertFailed();
    }

    private function buildWordPressSchema(): void
    {
        $schema = Schema::connection('legacy_wp');
        $schema->create('wp_terms', fn (Blueprint $t) => [$t->integer('term_id')->primary(), $t->string('name'), $t->string('slug')]);
        $schema->create('wp_term_taxonomy', fn (Blueprint $t) => [$t->integer('term_taxonomy_id')->primary(), $t->integer('term_id'), $t->string('taxonomy'), $t->integer('parent')->default(0), $t->text('description')->default('')]);
        $schema->create('wp_term_relationships', fn (Blueprint $t) => [$t->integer('object_id'), $t->integer('term_taxonomy_id')]);
        $schema->create('wp_posts', fn (Blueprint $t) => [$t->integer('ID')->primary(), $t->string('post_title'), $t->text('post_content')->default(''), $t->text('post_excerpt')->default(''), $t->string('post_status'), $t->string('post_type'), $t->integer('post_parent')->default(0), $t->integer('menu_order')->default(0), $t->string('guid')->default('')]);
        $schema->create('wp_postmeta', fn (Blueprint $t) => [$t->increments('meta_id'), $t->integer('post_id'), $t->string('meta_key'), $t->text('meta_value')]);
    }

    private function term(int $id, string $name, string $slug, string $taxonomy, int $parent = 0): int
    {
        $db = DB::connection('legacy_wp');
        $db->table('wp_terms')->insert(['term_id' => $id, 'name' => $name, 'slug' => $slug]);
        $db->table('wp_term_taxonomy')->insert(['term_taxonomy_id' => ++$this->taxonomyId, 'term_id' => $id, 'taxonomy' => $taxonomy, 'parent' => $parent]);

        return $this->taxonomyId;
    }

    /** @param array<string, string> $meta @param list<int> $taxonomies */
    private function wpPost(int $id, string $title, string $type, array $meta = [], array $taxonomies = [], string $status = 'publish', int $parent = 0): void
    {
        $db = DB::connection('legacy_wp');
        $db->table('wp_posts')->insert(['ID' => $id, 'post_title' => $title, 'post_status' => $status, 'post_type' => $type, 'post_parent' => $parent]);

        foreach ($meta as $key => $value) {
            $db->table('wp_postmeta')->insert(['post_id' => $id, 'meta_key' => $key, 'meta_value' => $value]);
        }

        foreach ($taxonomies as $tt) {
            $db->table('wp_term_relationships')->insert(['object_id' => $id, 'term_taxonomy_id' => $tt]);
        }
    }

    private function seedWordPress(): void
    {
        $this->term(1, 'Uncategorized', 'uncategorized', 'product_cat');
        $coffee = $this->term(2, 'قهوه', '%d9%82%d9%87%d9%88%d9%87', 'product_cat');
        $lattes = $this->term(3, 'لاته‌ها', 'lattes', 'product_cat', parent: 2);
        $simple = $this->term(10, 'simple', 'simple', 'product_type');
        $variable = $this->term(11, 'variable', 'variable', 'product_type');
        $grouped = $this->term(12, 'grouped', 'grouped', 'product_type');
        $featured = $this->term(13, 'featured', 'featured', 'product_visibility');
        $vegan = $this->term(14, 'Vegan', 'vegan', 'product_tag');
        $this->term(20, 'کوچک', 'small', 'pa_size');
        $this->term(21, 'بزرگ', 'large', 'pa_size');

        $this->wpPost(10, 'اسپرسو', 'product', [
            '_regular_price' => '65000', '_price' => '65000', '_lcm_product_calory' => '5',
            '_upsell_ids' => serialize([40]), '_sku' => 'ESP-1',
        ], [$coffee, $simple, $featured, $vegan]);

        $this->wpPost(20, 'لاته', 'product', [], [$lattes, $variable]);
        $this->wpPost(21, 'لاته - کوچک', 'product_variation', ['_regular_price' => '85000', 'attribute_pa_size' => 'small'], parent: 20);
        $this->wpPost(22, 'لاته - بزرگ', 'product_variation', ['_regular_price' => '105000', 'attribute_pa_size' => 'large'], parent: 20);

        $this->wpPost(30, 'آیس‌تی', 'product', ['_regular_price' => '100000', '_sale_price' => '80000', '_price' => '80000'], [$simple]);
        $this->wpPost(40, 'شکلات داغ', 'product', ['_regular_price' => '40000'], [$simple]);
        $this->wpPost(50, 'بسته‌ی هدیه', 'product', ['_price' => '300000'], [$grouped]);
        $this->wpPost(60, 'بی‌قیمت', 'product', [], [$simple]);
        $this->wpPost(70, 'زباله', 'product', ['_regular_price' => '1'], [$simple], status: 'trash');
    }
}
