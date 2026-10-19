<?php

namespace App\Modules\Occasions\Models;

use App\Modules\Occasions\Enums\OccasionStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A café's answer to one occurrence, and when it was told about it.
 *
 * @property string $id
 * @property string $occasion_date_id
 * @property ?OccasionStatus $status
 * @property ?Carbon $notified_at
 */
#[Fillable(['occasion_date_id', 'status', 'notified_at'])]
class TenantOccasion extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['status' => OccasionStatus::class, 'notified_at' => 'datetime'];
    }
}
