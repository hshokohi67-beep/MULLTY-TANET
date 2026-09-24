<?php

namespace App\Modules\Core\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One opening interval. weekday is ISO (1 = Monday … 7 = Sunday).
 * When closes_at <= opens_at the interval runs past midnight into the next day.
 *
 * @property int $weekday
 * @property string $opens_at
 * @property string $closes_at
 */
#[Fillable(['branch_id', 'weekday', 'opens_at', 'closes_at'])]
class BranchOpeningHour extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
