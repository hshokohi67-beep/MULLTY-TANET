<?php

namespace App\Modules\Operations\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property int $color
 * @property bool $is_active
 */
#[Fillable(['name', 'color', 'is_active'])]
class ExpenseCategory extends Model
{
    use BelongsToTenant, HasUlids;

    /** Offered to a café the first time it opens expenses. */
    public const DEFAULTS = ['اجاره', 'قبوض', 'تعمیرات و نگهداری', 'تبلیغات', 'حمل و نقل', 'مالیات و عوارض', 'متفرقه'];

    protected function casts(): array
    {
        return ['color' => 'integer', 'is_active' => 'boolean'];
    }
}
