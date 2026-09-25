<?php

namespace App\Modules\Advertising\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Impressions and clicks of one campaign on one local day (counters only, no raw events).
 *
 * @property string $id
 * @property string $campaign_id
 * @property Carbon $day
 * @property int $impressions
 * @property int $clicks
 */
#[Fillable(['campaign_id', 'day'])]
class AdDailyStat extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['day' => 'date', 'impressions' => 'integer', 'clicks' => 'integer'];
    }
}
