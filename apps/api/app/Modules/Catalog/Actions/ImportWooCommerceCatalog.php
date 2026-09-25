<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Enums\PriceChangeReason;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ImportMapping;
use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\Slugger;
use App\Modules\Catalog\Support\WooCommerce\WooCommerceReader;
use App\Support\Audit\AuditLogger;
use App\Support\Money\CurrencyUnit;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports a WooCommerce catalog (the legacy Live Cafe Menu store) into the current tenant.
 * Idempotent: records are matched through import_mappings, so re-running updates instead of duplicating.
 *
 * Mapping (discovery report §C.3):
 *  product_cat → categories (nested) · simple product → 1 default variant · variable product → named variants
 *  _regular_price (toman) → base price in rial · _upsell_ids → shared modifier group «افزودنی‌ها» ("add-ons")
 *  _lcm_product_calory → nutrition.calories · diet product_tag slugs → dietary_tags · featured flag
 */
final class ImportWooCommerceCatalog
{
    private const SOURCE = 'woocommerce';

    private const DIET_TAGS = [
        'vegan' => 'vegan', 'gluten-free' => 'gluten_free', 'dairy-free' => 'dairy_free',
        'sugar-free' => 'sugar_free', 'low-calorie' => 'low_calorie', 'spicy' => 'spicy', 'high-protein' => 'high_protein',
    ];

    /** @var array{categories: int, products: int, variants: int, modifier_groups: int, images: int, skipped: int} */
    private array $counts;

    /** @var list<string> */
    private array $warnings;

    public function __construct(
        private readonly SyncVariants $syncVariants,
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{counts: array<string, int>, warnings: list<string>}
     */
    public function handle(WooCommerceReader $reader, CurrencyUnit $sourceUnit = CurrencyUnit::Toman, bool $withImages = false, bool $dryRun = false): array
    {
        $this->counts = ['categories' => 0, 'products' => 0, 'variants' => 0, 'modifier_groups' => 0, 'images' => 0, 'skipped' => 0];
        $this->warnings = [];

        DB::beginTransaction();

        try {
            $categoryMap = $this->importCategories($reader);
            $productMap = $this->importProducts($reader, $categoryMap, $sourceUnit, $withImages);
            $this->importUpsells($reader, $productMap, $sourceUnit);

            if ($dryRun) {
                DB::rollBack();
            } else {
                $this->audit->record('catalog.imported', null, ['source' => self::SOURCE, 'counts' => $this->counts]);
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return ['counts' => $this->counts, 'warnings' => $this->warnings];
    }

    /** @return array<int, string> wp term_id → category id */
    private function importCategories(WooCommerceReader $reader): array
    {
        $map = [];

        foreach ($reader->categories() as $term) {
            if ($term->slug === 'uncategorized') {
                continue;
            }

            $parentId = $term->parent ? ($map[(int) $term->parent] ?? null) : null;
            $category = $this->mapped('category', (string) $term->term_id, Category::class) ?? new Category;

            $category->fill([
                'name' => html_entity_decode($term->name),
                'parent_id' => $parentId,
                'description' => $term->description !== '' ? mb_substr(strip_tags($term->description), 0, 500) : null,
                'sort' => count($map),
            ]);

            if (! $category->exists) {
                $category->slug = Slugger::unique(urldecode($term->slug), fn (string $s) => Category::query()->where('slug', $s)->exists());
            }

            $category->save();
            $this->remember('category', (string) $term->term_id, $category->id);
            $map[(int) $term->term_id] = $category->id;
            $this->counts['categories']++;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $categoryMap
     * @return array<int, string> wp product ID → product id
     */
    private function importProducts(WooCommerceReader $reader, array $categoryMap, CurrencyUnit $unit, bool $withImages): array
    {
        $map = [];

        foreach ($reader->products() as $row) {
            $meta = $reader->meta((int) $row->ID);
            $type = $reader->terms((int) $row->ID, 'product_type')->first()->slug ?? 'simple';

            if (! in_array($type, ['simple', 'variable'], true)) {
                $this->warnings[] = sprintf('محصول «%s» از نوع %s است و وارد نشد.', $row->post_title, $type);
                $this->counts['skipped']++;

                continue;
            }

            $variants = $type === 'variable'
                ? $this->variantsOf($reader, (int) $row->ID, $unit)
                : $this->simpleVariant($meta, $row->post_title, $unit);

            if ($variants === []) {
                $this->warnings[] = sprintf('محصول «%s» قیمت ندارد و وارد نشد.', $row->post_title);
                $this->counts['skipped']++;

                continue;
            }

            $product = $this->mapped('product', (string) $row->ID, Product::class) ?? new Product;
            $isNew = ! $product->exists;
            $tags = $reader->terms((int) $row->ID, 'product_tag')->pluck('slug')->all();
            $calories = (int) ($meta['_lcm_product_calory'] ?? 0);

            $product->fill([
                'name' => html_entity_decode($row->post_title),
                'description' => trim(strip_tags($row->post_excerpt ?: $row->post_content)) ?: null,
                'is_active' => $row->post_status === 'publish',
                'is_featured' => $reader->terms((int) $row->ID, 'product_visibility')->contains('slug', 'featured'),
                'sort' => (int) $row->menu_order,
                'nutrition' => $calories > 0 ? ['calories' => $calories] : null,
                'dietary_tags' => array_values(array_unique(array_filter(array_map(fn (string $t) => self::DIET_TAGS[$t] ?? null, $tags)))) ?: null,
            ]);

            if ($isNew) {
                $product->slug = Slugger::unique($product->name, fn (string $s) => Product::withTrashed()->where('slug', $s)->exists());
            }

            $product->save();
            $this->remember('product', (string) $row->ID, $product->id);

            $categoryIds = $reader->terms((int) $row->ID, 'product_cat')->map(fn ($t) => $categoryMap[(int) $t->term_id] ?? null)->filter()->values();
            $product->categories()->sync($categoryIds->mapWithKeys(fn (string $id, int $i) => [$id => ['tenant_id' => $product->tenant_id, 'sort' => $i]])->all());

            // Keep existing variant ids (matched by name) so re-imports update instead of recreating.
            $existing = ProductVariant::query()->where('product_id', $product->id)->get()->keyBy(fn (ProductVariant $v) => (string) $v->name);
            $variants = array_map(fn (VariantData $v) => new VariantData($existing->get((string) $v->name)?->id, $v->name, $v->basePrice, $v->sku, $v->isActive), $variants);
            $this->syncVariants->handle($product, $variants, null, PriceChangeReason::Import);

            if (! empty($meta['_sale_price']) && ! empty($meta['_regular_price']) && $meta['_sale_price'] !== $meta['_regular_price']) {
                $this->warnings[] = sprintf('محصول «%s» حراج داشت؛ قیمت اصلی (بدون حراج) وارد شد. در صورت نیاز یک تخفیف تعریف کنید.', $product->name);
            }

            if ($withImages && ! empty($meta['_thumbnail_id']) && ! ProductImage::query()->where('product_id', $product->id)->exists()) {
                $this->importImage($reader, $product, (int) $meta['_thumbnail_id']);
            }

            $map[(int) $row->ID] = $product->id;
            $this->counts['products']++;
            $this->counts['variants'] += count($variants);
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $meta
     * @return list<VariantData>
     */
    private function simpleVariant(array $meta, string $title, CurrencyUnit $unit): array
    {
        $price = $this->price($meta, $title, $unit);

        return $price === null ? [] : [new VariantData(null, null, $price, $this->sku($meta))];
    }

    /** @return list<VariantData> */
    private function variantsOf(WooCommerceReader $reader, int $productId, CurrencyUnit $unit): array
    {
        $variants = [];
        $names = [];

        foreach ($reader->variations($productId) as $variation) {
            $meta = $reader->meta((int) $variation->ID);
            $price = $this->price($meta, $variation->post_title, $unit);

            if ($price === null) {
                continue;
            }

            $labels = [];
            foreach ($meta as $key => $value) {
                if (str_starts_with($key, 'attribute_') && $value !== '') {
                    $taxonomy = substr($key, strlen('attribute_'));
                    $labels[] = str_starts_with($taxonomy, 'pa_') ? ($reader->attributeTermName($taxonomy, $value) ?? urldecode($value)) : urldecode($value);
                }
            }

            $name = mb_substr(implode(' / ', $labels) ?: 'نوع '.(count($variants) + 1), 0, 80);

            // Names must be unique within a product (they identify variants on re-import).
            for ($i = 2; in_array($name, $names, true); $i++) {
                $name = mb_substr(implode(' / ', $labels), 0, 76).' ('.$i.')';
            }

            $names[] = $name;
            $variants[] = new VariantData(null, $name, $price, $this->sku($meta), $variation->post_status === 'publish');
        }

        return $variants;
    }

    /** @param array<string, string> $meta */
    private function price(array $meta, string $title, CurrencyUnit $unit): ?int
    {
        $raw = ($meta['_regular_price'] ?? '') !== '' ? $meta['_regular_price'] : ($meta['_price'] ?? '');

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        if ((float) $raw != round((float) $raw)) {
            $this->warnings[] = sprintf('قیمت اعشاری «%s» برای «%s» گرد شد.', $raw, $title);
        }

        return Money::fromUnit((int) round((float) $raw), $unit)->rials;
    }

    /** @param array<string, string> $meta */
    private function sku(array $meta): ?string
    {
        $sku = trim($meta['_sku'] ?? '');

        if ($sku === '' || ! preg_match('/^[A-Za-z0-9._-]{1,64}$/', $sku)) {
            return null;
        }

        return ProductVariant::query()->where('sku', $sku)->exists() ? null : $sku;
    }

    /**
     * Upsells were the legacy "add-ons". Products sharing the same upsell set share one modifier group.
     *
     * @param  array<int, string>  $productMap
     */
    private function importUpsells(WooCommerceReader $reader, array $productMap, CurrencyUnit $unit): void
    {
        foreach ($productMap as $wpId => $productId) {
            $upsellIds = WooCommerceReader::parseIdList($reader->meta($wpId)['_upsell_ids'] ?? null);

            if ($upsellIds === []) {
                continue;
            }

            sort($upsellIds);
            $setKey = implode(',', $upsellIds);
            $group = $this->mapped('upsell_group', md5($setKey), ModifierGroup::class);

            if ($group === null) {
                $group = ModifierGroup::query()->create(['name' => 'افزودنی‌ها', 'min_select' => 0, 'max_select' => 0]);
                $this->remember('upsell_group', md5($setKey), $group->id);
                $this->counts['modifier_groups']++;

                foreach ($upsellIds as $i => $upsellId) {
                    $meta = $reader->meta($upsellId);
                    $name = $this->upsellName($upsellId);
                    $price = $this->price($meta, $name ?? '', $unit);

                    if ($name === null || $price === null) {
                        continue;
                    }

                    Modifier::query()->create(['modifier_group_id' => $group->id, 'name' => $name, 'price_delta' => $price, 'sort' => $i]);
                }
            }

            $product = Product::query()->find($productId);
            $product?->modifierGroups()->syncWithoutDetaching([$group->id => ['tenant_id' => $product->tenant_id, 'sort' => 0]]);
        }
    }

    private function upsellName(int $wpId): ?string
    {
        $mapped = $this->mapped('product', (string) $wpId, Product::class);

        return $mapped?->name;
    }

    private function importImage(WooCommerceReader $reader, Product $product, int $attachmentId): void
    {
        $url = $reader->attachmentUrl($attachmentId);

        if ($url === null || ! str_starts_with($url, 'http')) {
            return;
        }

        try {
            $response = Http::timeout(15)->get($url);
        } catch (Throwable) {
            $this->warnings[] = sprintf('دریافت تصویر «%s» ناموفق بود.', $product->name);

            return;
        }

        $mime = (string) $response->header('Content-Type');
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][strtok($mime, ';')] ?? null;

        if (! $response->successful() || $extension === null || strlen($response->body()) > 5 * 1024 * 1024) {
            $this->warnings[] = sprintf('تصویر «%s» معتبر نبود و وارد نشد.', $product->name);

            return;
        }

        $path = sprintf('%s/%s.%s', $this->context->require()->mediaDirectory('products'), Str::ulid(), $extension);
        Storage::disk(config('filesystems.media_disk'))->put($path, $response->body(), ['visibility' => 'public']);
        $size = @getimagesizefromstring($response->body()) ?: [null, null];

        ProductImage::query()->create(['product_id' => $product->id, 'path' => $path, 'alt' => $product->name, 'width' => $size[0], 'height' => $size[1], 'sort' => 0]);
        $this->counts['images']++;
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function mapped(string $type, string $sourceId, string $model): ?object
    {
        $targetId = ImportMapping::query()->where(['source' => self::SOURCE, 'source_type' => $type, 'source_id' => $sourceId])->value('target_id');

        return $targetId ? $model::query()->find($targetId) : null;
    }

    private function remember(string $type, string $sourceId, string $targetId): void
    {
        ImportMapping::query()->updateOrCreate(
            ['source' => self::SOURCE, 'source_type' => $type, 'source_id' => $sourceId],
            ['target_id' => $targetId],
        );
    }
}
