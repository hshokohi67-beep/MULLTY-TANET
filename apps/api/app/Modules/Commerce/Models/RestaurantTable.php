<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Core\Models\Branch;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property string $label
 * @property ?int $capacity
 * @property bool $is_active
 * @property int $sort
 * @property Branch $branch
 */
#[Fillable(['branch_id', 'label', 'capacity', 'is_active', 'sort'])]
class RestaurantTable extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<TableQrCode, $this> */
    public function qrCodes(): HasMany
    {
        return $this->hasMany(TableQrCode::class, 'table_id');
    }

    /** @return HasMany<OrderSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(OrderSession::class, 'table_id');
    }
}
