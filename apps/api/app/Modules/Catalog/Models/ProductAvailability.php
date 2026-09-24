<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\AvailabilityStatus;
use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-branch availability. No row = available.
 *
 * @property string $product_id
 * @property string $branch_id
 * @property AvailabilityStatus $status
 * @property ?Carbon $sold_out_until
 */
#[Table('product_availability')]
#[Fillable(['product_id', 'branch_id', 'status', 'sold_out_until'])]
class ProductAvailability extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['status' => AvailabilityStatus::class, 'sold_out_until' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }

    /** The status in effect right now ("sold out until 18:00" expires by itself). */
    public function effectiveStatus(?Carbon $now = null): AvailabilityStatus
    {
        if ($this->status === AvailabilityStatus::SoldOut && $this->sold_out_until !== null && $this->sold_out_until->lessThanOrEqualTo($now ?? now())) {
            return AvailabilityStatus::Available;
        }

        return $this->status;
    }
}
