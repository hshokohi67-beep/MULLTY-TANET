<?php

namespace App\Modules\Analytics\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $branch_id
 * @property Carbon $business_date
 * @property int $hour
 * @property int $orders
 * @property int $sales
 */
class HourlyMetric extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'hour' => 'integer', 'orders' => 'integer', 'sales' => 'integer'];
    }
}
