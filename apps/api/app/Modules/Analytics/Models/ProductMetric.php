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
 * @property ?string $product_id
 * @property string $product_name
 * @property int $quantity
 * @property int $revenue
 * @property int $cost
 * @property int $costed_quantity
 */
class ProductMetric extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'quantity' => 'integer', 'revenue' => 'integer', 'cost' => 'integer', 'costed_quantity' => 'integer'];
    }
}
