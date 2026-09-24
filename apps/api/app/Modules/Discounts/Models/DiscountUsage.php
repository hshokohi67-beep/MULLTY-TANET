<?php

namespace App\Modules\Discounts\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $discount_id
 * @property string $order_id
 * @property ?string $customer_id
 * @property int $amount
 */
#[Fillable(['discount_id', 'order_id', 'customer_id', 'amount'])]
class DiscountUsage extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
