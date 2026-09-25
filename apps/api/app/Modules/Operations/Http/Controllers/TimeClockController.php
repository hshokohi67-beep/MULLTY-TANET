<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Modules\Operations\Actions\ManageAttendance;
use App\Modules\Operations\Exceptions\OperationsException;
use App\Modules\Operations\Http\Resources\AttendanceResource;
use App\Modules\Operations\Models\AttendanceRecord;
use App\Modules\Operations\Models\Employee;
use App\Modules\Operations\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Self clock-in/out for a staff user who is linked to an employee of this café. */
final class TimeClockController
{
    public function me(Request $request): JsonResponse
    {
        $employee = Employee::query()->where('user_id', $request->user()?->getAuthIdentifier())->where('is_active', true)->first();
        if ($employee === null) {
            return response()->json(['data' => null]);
        }
        $open = AttendanceRecord::query()->with('shift')->where('employee_id', $employee->id)->whereNull('clock_out_at')->latest('clock_in_at')->first();
        $next = Shift::query()->where('employee_id', $employee->id)->where('ends_at', '>', now())->orderBy('starts_at')->first();

        return response()->json(['data' => [
            'employee' => ['id' => $employee->id, 'name' => $employee->name],
            'open' => $open ? (new AttendanceResource($open))->resolve() : null,
            'next_shift' => $next ? ['starts_at' => $next->starts_at->toIso8601String(), 'ends_at' => $next->ends_at->toIso8601String()] : null,
        ]]);
    }

    public function clockIn(Request $request, ManageAttendance $attendance): JsonResponse
    {
        return (new AttendanceResource($attendance->clockIn($this->employee($request))->load('shift')))->response()->setStatusCode(201);
    }

    public function clockOut(Request $request, ManageAttendance $attendance): AttendanceResource
    {
        return new AttendanceResource($attendance->clockOut($this->employee($request))->load('shift'));
    }

    private function employee(Request $request): Employee
    {
        return Employee::query()->where('user_id', $request->user()?->getAuthIdentifier())->where('is_active', true)->first()
            ?? throw OperationsException::notAnEmployee();
    }
}
