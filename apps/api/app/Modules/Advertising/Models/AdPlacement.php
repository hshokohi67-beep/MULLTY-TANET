<?php

namespace App\Modules\Advertising\Models;

use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Where ads can appear (platform-level): price per day in rial and how many campaigns may run
 * at the same time.
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string $description
 * @property int $daily_price
 * @property int $capacity
 * @property bool $requires_image
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['name', 'description', 'daily_price', 'capacity', 'is_active'])]
class AdPlacement extends Model
{
    use HasUlids, StoresDatesInUtc;

    public const HOME_BANNER = 'home_banner';

    public const SEARCH_TOP = 'search_top';

    protected function casts(): array
    {
        return ['daily_price' => 'integer', 'capacity' => 'integer', 'requires_image' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];
    }
}
