<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Enums\PriceChangeReason;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogSheet;
use App\Support\Audit\AuditLogger;
use App\Support\Entitlements\EntitlementGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «ورود از اکسل»: a spreadsheet of names, categories, prices (and optionally descriptions and
 * calories) into the menu. `plan()` only reads and reports what would happen; `apply()` does it.
 * Matching is by name, so uploading the same file again updates prices instead of duplicating:
 *  - a new name becomes a product with one size at that price, in its category (created if new);
 *  - a known name gets its price updated (single-size products only), and its description and
 *    calories when the sheet has them; it is added to the category, never removed from others.
 */
final class ImportCatalogSheet
{
    public const MAX_ITEMS = 2000;

    public function __construct(
        private readonly SaveProduct $saveProduct,
        private readonly SaveCategory $saveCategory,
        private readonly SetVariantPrice $setPrice,
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  list<list<string>>  $rows  header first
     * @param  array<string, int>  $columns  field => column index
     * @return array{columns: array<string, int>, rows: list<array<string, mixed>>, summary: array<string, int>, new_categories: list<string>}
     */
    public function plan(array $rows, array $columns, string $unit = 'toman'): array
    {
        $existing = $this->existingProducts();
        $knownCategories = Category::query()->pluck('name')->mapWithKeys(fn (string $n) => [CatalogSheet::key($n) => true])->all();
        $seen = [];
        $newCategories = [];
        $out = [];

        foreach (array_slice($rows, 1, self::MAX_ITEMS) as $i => $row) {
            $cell = fn (string $field): string => isset($columns[$field]) ? trim((string) ($row[$columns[$field]] ?? '')) : '';
            $line = $i + 2; // spreadsheet row number (header is row 1)
            $name = mb_substr(preg_replace('/\s+/u', ' ', $cell('name')) ?? '', 0, 120);
            $path = CatalogSheet::categoryPath($cell('category'));
            $amount = CatalogSheet::amount($cell('price'));
            $price = $amount === null ? null : ($unit === 'rial' ? $amount : $amount * 10);
            $calories = CatalogSheet::amount($cell('calories'));
            $description = mb_substr($cell('description'), 0, 500);
            $type = mb_strtolower($cell('type'));
            $item = ['line' => $line, 'name' => $name, 'category' => $path, 'price' => $price, 'description' => $description !== '' ? $description : null, 'calories' => $calories && $calories < 5000 ? $calories : null, 'problems' => []];

            if ($name === '') {
                continue; // an empty line in the middle of the sheet
            }
            if (in_array($type, ['variable', 'variation', 'grouped'], true)) {
                $out[] = [...$item, 'status' => 'skip', 'problems' => ['محصول چندسایزی ووکامرس: سایزها را بعد از ورود در صفحه‌ی محصول اضافه کنید.']];

                continue;
            }
            $key = CatalogSheet::key($name);
            if (isset($seen[$key])) {
                $out[] = [...$item, 'status' => 'skip', 'problems' => ["تکراری: همین نام در ردیف {$seen[$key]} هم آمده است."]];

                continue;
            }
            $seen[$key] = $line;

            $current = $existing[$key] ?? null;
            if ($current === null) {
                if ($price === null || $price <= 0) {
                    $out[] = [...$item, 'status' => 'error', 'problems' => ['قیمت ندارد یا عدد نیست.']];

                    continue;
                }
                foreach ($path as $depth => $part) {
                    $k = CatalogSheet::key(implode(' > ', array_slice($path, 0, $depth + 1)));
                    if (! isset($knownCategories[CatalogSheet::key($part)]) && ! isset($newCategories[$k])) {
                        $newCategories[$k] = implode(' > ', array_slice($path, 0, $depth + 1));
                    }
                }
                $out[] = [...$item, 'status' => 'create'];

                continue;
            }

            $changes = [];
            if ($price !== null && $price > 0 && $current['price'] !== $price) {
                if ($current['variants'] === 1) {
                    $changes['price'] = ['from' => $current['price'], 'to' => $price];
                } else {
                    $item['problems'][] = 'این محصول چند سایز دارد؛ قیمتش را در صفحه‌ی محصول عوض کنید.';
                }
            }
            if ($item['description'] !== null && $item['description'] !== $current['description']) {
                $changes['description'] = true;
            }
            if ($item['calories'] !== null && $item['calories'] !== $current['calories']) {
                $changes['calories'] = true;
            }
            $out[] = [...$item, 'status' => $changes === [] ? 'same' : 'update', 'changes' => $changes, 'product_id' => $current['id']];
        }

        $count = fn (string $s) => count(array_filter($out, fn (array $r) => $r['status'] === $s));

        return [
            'columns' => $columns,
            'rows' => $out,
            'summary' => ['create' => $count('create'), 'update' => $count('update'), 'same' => $count('same'), 'error' => $count('error'), 'skip' => $count('skip'), 'total' => count($out)],
            'new_categories' => array_values($newCategories),
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, int>  $columns
     * @return array{created: int, updated: int, categories: int, not_created: int}
     */
    public function apply(array $rows, array $columns, string $unit, ?string $actorId): array
    {
        $plan = $this->plan($rows, $columns, $unit);
        $gate = app(EntitlementGate::class);
        $limit = $gate->limit('products');
        $count = Product::query()->count();
        $batch = (string) Str::ulid();
        $result = ['created' => 0, 'updated' => 0, 'categories' => 0, 'not_created' => 0];

        DB::transaction(function () use ($plan, $limit, &$count, $batch, $actorId, &$result): void {
            $categories = Category::query()->get(['id', 'name', 'parent_id']);
            $byKey = [];
            foreach ($categories as $c) {
                $byKey[CatalogSheet::key($c->name)] ??= $c->id;
            }

            $categoryFor = function (array $path) use (&$byKey, &$result): array {
                $ids = [];
                $parent = null;
                foreach ($path as $part) {
                    $key = CatalogSheet::key($part);
                    if (! isset($byKey[$key])) {
                        $byKey[$key] = $this->saveCategory->handle(['name' => $part, 'parent_id' => $parent])->id;
                        $result['categories']++;
                    }
                    $parent = $byKey[$key];
                    $ids = [$parent]; // the product sits in the deepest category
                }

                return $ids;
            };

            foreach ($plan['rows'] as $row) {
                if ($row['status'] === 'create') {
                    if ($limit !== null && $count >= $limit) {
                        $result['not_created']++; // the plan's product limit; the rest stays out

                        continue;
                    }
                    $this->saveProduct->handle(
                        new ProductData(name: $row['name'], description: $row['description'], categoryIds: $categoryFor($row['category']), nutrition: $row['calories'] ? ['calories' => $row['calories']] : null),
                        null,
                        [new VariantData(null, null, (int) $row['price'])],
                        $actorId,
                    );
                    $count++;
                    $result['created']++;
                } elseif ($row['status'] === 'update') {
                    $product = Product::query()->with(['variants' => fn ($q) => $q->where('is_active', true)])->find($row['product_id']);
                    if ($product === null) {
                        continue;
                    }
                    if (isset($row['changes']['price']) && $product->variants->count() === 1) {
                        $this->setPrice->handle($product->variants->first(), null, (int) $row['price'], PriceChangeReason::Import, $actorId, $batch);
                    }
                    if (isset($row['changes']['description'])) {
                        $product->description = $row['description'];
                    }
                    if (isset($row['changes']['calories'])) {
                        $product->nutrition = [...($product->nutrition ?? []), 'calories' => $row['calories']];
                    }
                    $product->save();
                    if ($row['category'] !== []) {
                        $tenantId = $this->context->require()->getKey();
                        $product->categories()->syncWithoutDetaching(collect($categoryFor($row['category']))->mapWithKeys(fn (string $id) => [$id => ['tenant_id' => $tenantId]])->all());
                    }
                    $result['updated']++;
                }
            }
        });

        $this->audit->record('catalog.imported', null, $result);

        return $result;
    }

    /** @return array<string, array{id: string, price: ?int, variants: int, description: ?string, calories: ?int}> */
    private function existingProducts(): array
    {
        $out = [];
        foreach (Product::query()->with(['variants' => fn ($q) => $q->where('is_active', true)->with('prices')])->get() as $p) {
            $variant = $p->variants->first();
            $out[CatalogSheet::key($p->name)] ??= [
                'id' => $p->id,
                'price' => $variant?->basePrice()?->amount,
                'variants' => $p->variants->count(),
                'description' => $p->description,
                'calories' => isset($p->nutrition['calories']) ? (int) $p->nutrition['calories'] : null,
            ];
        }

        return $out;
    }
}
