<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $modifier_group_id
 * @property string $name
 * @property int $price_delta rial
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['modifier_group_id', 'name', 'price_delta', 'is_default', 'is_active', 'sort'])]
class Modifier extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['price_delta' => 'integer', 'is_default' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }
}
