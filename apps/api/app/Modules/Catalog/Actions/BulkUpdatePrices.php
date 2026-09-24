<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\PriceChangeReason;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\BulkPriceOperation;
use App\Modules\Catalog\Support\PriceResolver;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk price change (±percent, ±fixed, exact) over products, categories or the whole menu,
 * on base prices or one branch. Preview returns the same rows without writing anything.
 * Apply is one transaction; every change is logged with a shared batch_id and summarised in the audit log.
 */
final class BulkUpdatePrices
{
    public function __construct(
        private readonly SetVariantPrice $setPrice,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{product_ids?: list<string>, category_ids?: list<string>, all?: bool}  $target
     * @return array{batch_id: ?string, rows: list<array{variant_id: string, product_id: string, product: string, variant: ?string, old_amount: int, new_amount: int}>}
     */
    public function handle(array $target, ?string $branchId, BulkPriceOperation $operation, int $value, int $roundTo, bool $preview, ?string $actorId): array
    {
        $run = function () use ($target, $branchId, $operation, $value, $roundTo, $preview, $actorId): array {
            $variants = $this->variants($target, lock: ! $preview);
            $batchId = $preview ? null : (string) Str::ulid();
            $rows = [];

            foreach ($variants as $variant) {
                // A branch-scoped change starts from what the branch currently charges.
                $current = PriceResolver::amountFor($variant, $branchId);

                if ($current === null) {
                    continue;
                }

                $new = $operation->apply($current, $value, $roundTo);

                if ($new === $current) {
                    continue;
                }

                $rows[] = [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product' => $variant->product->name,
                    'variant' => $variant->name,
                    'old_amount' => $current,
                    'new_amount' => $new,
                ];

                if (! $preview) {
                    $this->setPrice->handle($variant, $branchId, $new, PriceChangeReason::Bulk, $actorId, $batchId);
                }
            }

            if (! $preview && $rows !== []) {
                $this->audit->record('prices.bulk_updated', null, [
                    'batch_id' => $batchId,
                    'operation' => $operation->value,
                    'value' => $value,
                    'round_to' => $roundTo,
                    'branch_id' => $branchId,
                    'target' => $target,
                    'changed' => count($rows),
                ]);
            }

            return ['batch_id' => $batchId, 'rows' => $rows];
        };

        return $preview ? $run() : DB::transaction($run);
    }

    /**
     * @param  array{product_ids?: list<string>, category_ids?: list<string>, all?: bool}  $target
     * @return Collection<int, ProductVariant>
     */
    private function variants(array $target, bool $lock): Collection
    {
        $query = ProductVariant::query()
            ->with(['prices', 'product'])
            // The variant→product relation includes trashed products; deleted products must not change.
            ->whereHas('product', fn ($q) => $q->whereNull('products.deleted_at'))
            ->orderBy('product_id')
            ->orderBy('sort');

        if (empty($target['all'])) {
            $productIds = $target['product_ids'] ?? [];
            $categoryIds = collect($target['category_ids'] ?? [])
                ->flatMap(fn (string $id) => Category::query()->findOrFail($id)->selfAndDescendantIds())
                ->unique()->values()->all();

            $query->where(function ($q) use ($productIds, $categoryIds): void {
                $q->whereIn('product_id', $productIds)
                    ->orWhereHas('product.categories', fn ($c) => $c->whereIn('categories.id', $categoryIds));
            });
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }
}
