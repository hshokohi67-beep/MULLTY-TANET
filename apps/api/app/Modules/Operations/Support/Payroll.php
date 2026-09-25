<?php

namespace App\Modules\Operations\Support;

use App\Modules\Operations\Models\AttendanceRecord;
use App\Modules\Operations\Models\Employee;
use App\Modules\Operations\Models\Shift;
use Carbon\CarbonImmutable;

/**
 * Worked time and labour cost between two instants: attendance minutes (open records count up
 * to now) priced at each person's hourly rate (monthly salaries spread over standard hours).
 */
final class Payroll
{
    /** @return list<array{employee_id: string, name: string, position: ?string, pay_type: string, minutes: int, shifts: int, late: int, open: bool, cost: int}> */
    public static function between(CarbonImmutable $from, CarbonImmutable $to, ?string $branchId = null): array
    {
        $records = AttendanceRecord::query()->with('shift')
            ->where('clock_in_at', '>=', $from)->where('clock_in_at', '<', $to)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->get()->groupBy('employee_id');
        $shifts = Shift::query()->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->selectRaw('employee_id, count(*) as n')->groupBy('employee_id')->pluck('n', 'employee_id');

        $employees = Employee::query()->whereIn('id', [...$records->keys(), ...$shifts->keys()])->orderBy('name')->get();

        return $employees->map(function (Employee $e) use ($records, $shifts): array {
            $mine = $records->get($e->id, collect());
            $minutes = (int) $mine->sum(fn (AttendanceRecord $r) => $r->minutes());

            return [
                'employee_id' => $e->id,
                'name' => $e->name,
                'position' => $e->position,
                'pay_type' => $e->pay_type,
                'minutes' => $minutes,
                'shifts' => (int) ($shifts[$e->id] ?? 0),
                'late' => $mine->filter(fn (AttendanceRecord $r) => $r->lateMinutes() > 0)->count(),
                'open' => $mine->contains(fn (AttendanceRecord $r) => $r->clock_out_at === null),
                'cost' => $e->costOfMinutes($minutes),
            ];
        })->values()->all();
    }

    public static function cost(CarbonImmutable $from, CarbonImmutable $to, ?string $branchId = null): int
    {
        return array_sum(array_column(self::between($from, $to, $branchId), 'cost'));
    }
}
