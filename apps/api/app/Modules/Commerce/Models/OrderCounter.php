<?php

namespace App\Modules\Commerce\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $branch_id
 * @property string $date
 * @property int $last_number
 */
#[Fillable(['branch_id', 'date', 'last_number'])]
class OrderCounter extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
