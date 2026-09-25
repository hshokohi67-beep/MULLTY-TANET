<?php

namespace App\Modules\Operations\Actions;

use App\Modules\Operations\Exceptions\OperationsException;
use App\Modules\Operations\Models\AttendanceRecord;
use App\Modules\Operations\Models\Employee;
use App\Modules\Operations\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Clock in / out (self or by a manager) and manual corrections. A record is matched to the shift
 * it belongs to (the person's shift that starts within two hours of the clock-in, or covers it),
 * which is what "late" is measured against.
 */
final class ManageAttendance
{
    public function clockIn(Employee $employee, string $source = 'self', ?string $note = null): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $source, $note): AttendanceRecord {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            if (AttendanceRecord::query()->where('employee_id', $employee->id)->whereNull('clock_out_at')->exists()) {
                throw OperationsException::alreadyClockedIn();
            }

            $now = CarbonImmutable::now();

            return AttendanceRecord::query()->create([
                'employee_id' => $employee->id,
                'branch_id' => $employee->branch_id,
                'shift_id' => $this->matchShift($employee->id, $now)?->id,
                'clock_in_at' => $now,
                'source' => $source,
                'note' => $note,
            ]);
        });
    }

    public function clockOut(Employee $employee): AttendanceRecord
    {
        return DB::transaction(function () use ($employee): AttendanceRecord {
            /** @var AttendanceRecord|null $open */
            $open = AttendanceRecord::query()->where('employee_id', $employee->id)->whereNull('clock_out_at')->lockForUpdate()->latest('clock_in_at')->first();
            if ($open === null) {
                throw OperationsException::notClockedIn();
            }
            $open->forceFill(['clock_out_at' => now()])->save();

            return $open;
        });
    }

    /**
     * A manager adds or corrects a record.
     *
     * @param  array{employee_id: string, clock_in_at: string, clock_out_at?: ?string, note?: ?string}  $data
     */
    public function save(array $data, ?AttendanceRecord $record, string $editorId): AttendanceRecord
    {
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $in = CarbonImmutable::parse($data['clock_in_at'])->utc();
        $out = ! empty($data['clock_out_at']) ? CarbonImmutable::parse($data['clock_out_at'])->utc() : null;

        $record ??= new AttendanceRecord(['source' => 'manager']);
        $record->fill([
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'shift_id' => $this->matchShift($employee->id, $in)?->id,
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'note' => $data['note'] ?? $record->note,
            'edited_by' => $editorId,
        ])->save();

        return $record;
    }

    private function matchShift(string $employeeId, CarbonImmutable $at): ?Shift
    {
        return Shift::query()->where('employee_id', $employeeId)
            ->where('starts_at', '<=', $at->addHours(2))
            ->where('ends_at', '>', $at)
            ->orderBy('starts_at')
            ->first();
    }
}
