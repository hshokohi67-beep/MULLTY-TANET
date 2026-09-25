<?php

namespace App\Modules\Operations\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $employee_id
 * @property string $branch_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property ?string $note
 * @property Employee $employee
 */
#[Fillable(['employee_id', 'branch_id', 'starts_at', 'ends_at', 'note'])]
class Shift extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
