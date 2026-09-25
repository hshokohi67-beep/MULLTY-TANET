<?php

namespace App\Modules\Operations\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class AttendanceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', TenantExists::in('employees')],
            'clock_in_at' => ['required', 'date', 'before_or_equal:now'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['employee_id' => 'کارمند', 'clock_in_at' => 'زمان ورود', 'clock_out_at' => 'زمان خروج'];
    }

    /** @return array{employee_id: string, clock_in_at: string, clock_out_at?: ?string, note?: ?string} */
    public function record(): array
    {
        /** @var array{employee_id: string, clock_in_at: string, clock_out_at?: ?string, note?: ?string} */
        return $this->validated();
    }
}
