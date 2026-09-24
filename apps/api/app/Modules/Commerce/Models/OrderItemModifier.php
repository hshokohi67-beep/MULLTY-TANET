<?php

namespace App\Modules\Commerce\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ?string $modifier_id
 * @property string $group_name
 * @property string $name
 * @property int $price_delta
 */
#[Fillable(['order_item_id', 'modifier_id', 'group_name', 'name', 'price_delta'])]
class OrderItemModifier extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['price_delta' => 'integer'];
    }
}
