<?php

namespace App\Modules\Analytics\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One branch's business day, pre-aggregated (amounts in rial). Written only by `RollupDay`.
 *
 * @property string $id
 * @property string $branch_id
 * @property Carbon $business_date
 * @property int $orders
 * @property int $sales
 * @property int $discounts
 * @property int $refunds
 * @property int $cancelled
 * @property int $items
 * @property int $buyers
 * @property array<string, array{orders: int, sales: int}> $channels
 * @property array<string, int> $payments
 * @property int $cogs
 * @property int $item_lines
 * @property int $costed_lines
 * @property int $labour
 * @property int $expenses
 * @property int $waste
 * @property Carbon $computed_at
 */
class DailyMetric extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date', 'channels' => 'array', 'payments' => 'array', 'computed_at' => 'datetime',
            'orders' => 'integer', 'sales' => 'integer', 'discounts' => 'integer', 'refunds' => 'integer', 'cancelled' => 'integer',
            'items' => 'integer', 'buyers' => 'integer', 'cogs' => 'integer', 'item_lines' => 'integer', 'costed_lines' => 'integer',
            'labour' => 'integer', 'expenses' => 'integer', 'waste' => 'integer',
        ];
    }
}
