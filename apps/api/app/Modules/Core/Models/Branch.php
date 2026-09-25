<?php

namespace App\Modules\Core\Models;

use App\Support\Tenancy\BelongsToTenant;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'phone', 'province', 'city', 'district', 'address', 'postal_code', 'latitude', 'longitude', 'is_active', 'sort'])]
#[UseFactory(BranchFactory::class)]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'sort' => 'integer',
        ];
    }

    /** @return HasMany<BranchOpeningHour, $this> */
    public function openingHours(): HasMany
    {
        return $this->hasMany(BranchOpeningHour::class)->orderBy('weekday')->orderBy('opens_at');
    }
}
