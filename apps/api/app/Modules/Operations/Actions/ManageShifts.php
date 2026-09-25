<?php

namespace App\Modules\Operations\Actions;

use App\Modules\Operations\Exceptions\OperationsException;
use App\Modules\Operations\Models\Employee;
use App\Modules\Operations\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The shift schedule: no overlapping shifts per person, overnight shifts allowed (up to 16 h),
 * and copying a whole week forward (skipping anything that would clash).
 */
final class ManageShifts
{
    private const MAX_HOURS = 16;

    /** @param  array{employee_id: string, starts_at: string, ends_at: string, note?: ?string}  $data */
    public function save(array $data, ?Shift $shift = null): Shift
    {
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $start = CarbonImmutable::parse($data['starts_at'])->utc();
        $end = CarbonImmutable::parse($data['ends_at'])->utc();

        if ($end->lessThanOrEqualTo($start) || $start->diffInMinutes($end) > self::MAX_HOURS * 60) {
            throw OperationsException::shiftTooLong();
        }

        return DB::transaction(function () use ($employee, $start, $end, $data, $shift): Shift {
            // Lock the person's shifts so two edits can't create an overlap together.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            if ($this->overlaps($employee->id, $start, $end, $shift?->id)) {
                throw OperationsException::shiftOverlap($employee->name);
            }

            $shift ??= new Shift;
            $shift->fill([
                'employee_id' => $employee->id,
                'branch_id' => $employee->branch_id,
                'starts_at' => $start,
                'ends_at' => $end,
                'note' => $data['note'] ?? null,
            ])->save();

            return $shift;
        });
    }

    /**
     * Copies the shifts of the week starting $from (tenant-local date) to the week starting $to.
     *
     * @return array{copied: int, skipped: int}
     */
    public function copyWeek(string $from, string $to, string $timezone, ?string $branchId): array
    {
        $fromStart = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $offsetDays = (int) $fromStart->diffInDays(CarbonImmutable::parse($to, $timezone)->startOfDay(), false);
        $copied = 0;
        $skipped = 0;

        $shifts = Shift::query()->with('employee')
            ->where('starts_at', '>=', $fromStart->utc())->where('starts_at', '<', $fromStart->addDays(7)->utc())
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->get();

        foreach ($shifts as $s) {
            if (! $s->employee->is_active) {
                continue;
            }
            $start = CarbonImmutable::instance($s->starts_at)->addDays($offsetDays);
            $end = CarbonImmutable::instance($s->ends_at)->addDays($offsetDays);
            if ($this->overlaps($s->employee_id, $start, $end, null)) {
                $skipped++;

                continue;
            }
            Shift::query()->create(['employee_id' => $s->employee_id, 'branch_id' => $s->branch_id, 'starts_at' => $start, 'ends_at' => $end, 'note' => $s->note]);
            $copied++;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    private function overlaps(string $employeeId, CarbonImmutable $start, CarbonImmutable $end, ?string $ignoreId): bool
    {
        return Shift::query()->where('employee_id', $employeeId)
            ->when($ignoreId, fn ($q, $id) => $q->whereKeyNot($id))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->exists();
    }
}
