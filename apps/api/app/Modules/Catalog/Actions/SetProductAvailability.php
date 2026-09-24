<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\AvailabilityStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAvailability;
use App\Modules\Core\Models\Branch;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonInterface;

/**
 * "Sold out" / "hidden" for one branch. Setting Available removes the row (the default).
 */
final class SetProductAvailability
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Product $product, Branch $branch, AvailabilityStatus $status, ?CarbonInterface $soldOutUntil = null): void
    {
        $query = ProductAvailability::query()->where('product_id', $product->getKey())->where('branch_id', $branch->getKey());

        if ($status === AvailabilityStatus::Available) {
            $query->first()?->delete();
        } else {
            ProductAvailability::query()->updateOrCreate(
                ['product_id' => $product->getKey(), 'branch_id' => $branch->getKey()],
                ['status' => $status, 'sold_out_until' => $status === AvailabilityStatus::SoldOut ? $soldOutUntil : null],
            );
        }

        $this->audit->record('product.availability_changed', $product, [
            'branch_id' => $branch->getKey(),
            'status' => $status->value,
            'sold_out_until' => $soldOutUntil?->toIso8601String(),
        ]);
    }
}
