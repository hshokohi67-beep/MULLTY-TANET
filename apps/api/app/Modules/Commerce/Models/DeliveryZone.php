<?php

namespace App\Modules\Commerce\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A branch's delivery area. V1 supports radius zones; `polygon` is reserved for polygon zones.
 * All amounts are integer rial.
 *
 * @property string $id
 * @property string $branch_id
 * @property string $name
 * @property string $type
 * @property ?int $radius_m
 * @property int $delivery_fee
 * @property ?int $free_delivery_min
 * @property int $min_order
 * @property ?int $eta_minutes
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['branch_id', 'name', 'type', 'radius_m', 'polygon', 'delivery_fee', 'free_delivery_min', 'min_order', 'eta_minutes', 'is_active', 'sort'])]
class DeliveryZone extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'radius_m' => 'integer',
            'polygon' => 'array',
            'delivery_fee' => 'integer',
            'free_delivery_min' => 'integer',
            'min_order' => 'integer',
            'eta_minutes' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
