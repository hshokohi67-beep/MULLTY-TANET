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
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $branch_id
 * @property ?string $shift_id
 * @property Carbon $clock_in_at
 * @property ?Carbon $clock_out_at
 * @property string $source self|manager
 * @property ?string $note
 * @property ?string $edited_by
 * @property Employee $employee
 * @property ?Shift $shift
 */
#[Fillable(['employee_id', 'branch_id', 'shift_id', 'clock_in_at', 'clock_out_at', 'source', 'note', 'edited_by'])]
class AttendanceRecord extends Model
{
    use BelongsToTenant, HasUlids;

    /** Clocking in this long after the shift start counts as late. */
    public const LATE_AFTER_MINUTES = 10;

    protected function casts(): array
    {
        return ['clock_in_at' => 'datetime', 'clock_out_at' => 'datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** Worked minutes; an open record counts up to $now. */
    public function minutes(?Carbon $now = null): int
    {
        $end = $this->clock_out_at ?? $now ?? now();

        return max(0, (int) floor($this->clock_in_at->diffInSeconds($end, false) / 60));
    }

    public function lateMinutes(): int
    {
        if ($this->shift === null) {
            return 0;
        }
        $late = (int) floor($this->shift->starts_at->diffInSeconds($this->clock_in_at, false) / 60);

        return $late > self::LATE_AFTER_MINUTES ? $late : 0;
    }
}
