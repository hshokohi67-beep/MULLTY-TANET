<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Operations\Actions\ManageAttendance;
use App\Modules\Operations\Actions\ManageShifts;
use App\Modules\Operations\Exceptions\OperationsException;
use App\Modules\Operations\Http\Requests\AttendanceRequest;
use App\Modules\Operations\Http\Requests\EmployeeRequest;
use App\Modules\Operations\Http\Requests\ShiftRequest;
use App\Modules\Operations\Http\Resources\AttendanceResource;
use App\Modules\Operations\Http\Resources\EmployeeResource;
use App\Modules\Operations\Http\Resources\ShiftResource;
use App\Modules\Operations\Models\AttendanceRecord;
use App\Modules\Operations\Models\Employee;
use App\Modules\Operations\Models\Shift;
use App\Modules\Operations\Support\LocalRange;
use App\Modules\Operations\Support\Payroll;
use App\Support\Localization\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\TenantExists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Employees, the shift schedule, attendance records and payroll (all `staff.manage`). */
final class StaffController
{
    public function employees(): AnonymousResourceCollection
    {
        return EmployeeResource::collection(Employee::query()->orderByDesc('is_active')->orderBy('name')->get());
    }

    public function storeEmployee(EmployeeRequest $request): JsonResponse
    {
        return (new EmployeeResource(Employee::query()->create($this->employeeData($request))))->response()->setStatusCode(201);
    }

    public function updateEmployee(EmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->update($this->employeeData($request));

        return new EmployeeResource($employee);
    }

    public function shifts(Request $request): AnonymousResourceCollection
    {
        [$from, $to] = LocalRange::from($request, 7);
        $branchId = $request->validate(['branch_id' => ['nullable', 'string', 'max:26']])['branch_id'] ?? null;

        return ShiftResource::collection(Shift::query()->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->orderBy('starts_at')->get());
    }

    public function storeShift(ShiftRequest $request, ManageShifts $shifts): JsonResponse
    {
        return (new ShiftResource($shifts->save($request->shift())))->response()->setStatusCode(201);
    }

    public function updateShift(ShiftRequest $request, Shift $shift, ManageShifts $shifts): ShiftResource
    {
        return new ShiftResource($shifts->save($request->shift(), $shift));
    }

    public function destroyShift(Shift $shift): Response
    {
        $shift->delete();

        return response()->noContent();
    }

    public function copyWeek(Request $request, ManageShifts $shifts, TenantContext $context): JsonResponse
    {
        $v = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'different:from'],
            'branch_id' => ['nullable', 'string', TenantExists::in('branches')],
        ]);

        return response()->json(['data' => $shifts->copyWeek($v['from'], $v['to'], $context->require()->timezone, $v['branch_id'] ?? null)]);
    }

    public function attendance(Request $request): AnonymousResourceCollection
    {
        [$from, $to] = LocalRange::from($request, 1);
        $f = $request->validate(['employee_id' => ['nullable', 'string', 'max:26'], 'branch_id' => ['nullable', 'string', 'max:26']]);

        return AttendanceResource::collection(AttendanceRecord::query()->with(['employee', 'shift'])
            ->where(fn ($q) => $q->whereBetween('clock_in_at', [$from, $to])->orWhereNull('clock_out_at'))
            ->when($f['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderByDesc('clock_in_at')->limit(500)->get());
    }

    public function storeAttendance(AttendanceRequest $request, ManageAttendance $attendance): JsonResponse
    {
        $record = $attendance->save($request->record(), null, (string) $request->user()?->getAuthIdentifier());

        return (new AttendanceResource($record->load(['employee', 'shift'])))->response()->setStatusCode(201);
    }

    public function updateAttendance(AttendanceRequest $request, AttendanceRecord $attendanceRecord, ManageAttendance $attendance): AttendanceResource
    {
        return new AttendanceResource($attendance->save($request->record(), $attendanceRecord, (string) $request->user()?->getAuthIdentifier())->load(['employee', 'shift']));
    }

    public function destroyAttendance(AttendanceRecord $attendanceRecord): Response
    {
        $attendanceRecord->delete();

        return response()->noContent();
    }

    public function payroll(Request $request): JsonResponse
    {
        [$from, $to, $fromDate, $toDate] = LocalRange::from($request, 30);
        $branchId = $request->validate(['branch_id' => ['nullable', 'string', 'max:26']])['branch_id'] ?? null;
        $rows = Payroll::between($from, $to, $branchId);

        return response()->json(['data' => [
            'from' => $fromDate,
            'to' => $toDate,
            'rows' => $rows,
            'total_minutes' => array_sum(array_column($rows, 'minutes')),
            'total_cost' => array_sum(array_column($rows, 'cost')),
        ]]);
    }

    /** @return array<string, mixed> */
    private function employeeData(EmployeeRequest $request): array
    {
        $v = $request->validated();

        // Only a member of this café's team can be linked (for self clock-in).
        if (! empty($v['user_id']) && ! TenantUser::query()->where('user_id', $v['user_id'])->exists()) {
            throw OperationsException::userNotMember();
        }

        $v['phone_e164'] = PhoneNormalizer::tryNormalize($v['phone'] ?? null);
        unset($v['phone']);

        return $v;
    }
}
