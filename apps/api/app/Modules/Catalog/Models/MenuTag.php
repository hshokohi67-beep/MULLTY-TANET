<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\CatalogVersion;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A diet/allergy tag the café added itself, next to the platform's `Product::DIETARY_TAGS`.
 * Products refer to it as "c:{id}" in `dietary_tags`.
 *
 * @property string $id
 * @property string $label
 */
#[Fillable(['label'])]
class MenuTag extends Model
{
    use BelongsToTenant, HasUlids;

    public const PREFIX = 'c:';

    public const MAX_PER_TENANT = 40;

    public function key(): string
    {
        return self::PREFIX.$this->id;
    }

    protected static function booted(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }
}
