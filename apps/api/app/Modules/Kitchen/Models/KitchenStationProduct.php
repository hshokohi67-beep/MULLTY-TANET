<?php

namespace App\Modules\Kitchen\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $branch_id
 * @property string $station_id
 * @property string $product_id
 */
#[Fillable(['branch_id', 'station_id', 'product_id'])]
class KitchenStationProduct extends Model
{
    use BelongsToTenant, HasUlids;
}
