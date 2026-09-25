<?php

namespace App\Modules\Analytics\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A business day whose source data changed after its last rollup.
 *
 * @property string $id
 * @property Carbon $business_date
 */
class MetricDirtyDay extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['business_date' => 'date'];
    }
}
