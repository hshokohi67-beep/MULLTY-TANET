<?php

namespace App\Modules\Kitchen\Models;

use App\Modules\Core\Models\Branch;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place in the kitchen with its own screen ("coffee bar", "kitchen", "desserts").
 *
 * @property string $id
 * @property string $branch_id
 * @property string $name
 * @property bool $is_default
 * @property int $late_after_minutes
 * @property bool $is_active
 * @property int $sort
 * @property Branch $branch
 */
#[Fillable(['branch_id', 'name', 'is_default', 'late_after_minutes', 'is_active', 'sort'])]
class KitchenStation extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'late_after_minutes' => 'integer', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<KitchenStationProduct, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(KitchenStationProduct::class, 'station_id');
    }
}
