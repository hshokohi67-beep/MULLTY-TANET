<?php

namespace App\Modules\Analytics\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Orders of one business day and branch that held both products (product_a < product_b).
 *
 * @property string $id
 * @property string $branch_id
 * @property Carbon $business_date
 * @property string $product_a
 * @property string $product_b
 * @property int $orders
 */
class ProductPairMetric extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'orders' => 'integer'];
    }
}
