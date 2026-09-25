<?php

namespace App\Modules\Operations\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class ShiftRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', TenantExists::in('employees')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['employee_id' => 'کارمند', 'starts_at' => 'شروع', 'ends_at' => 'پایان'];
    }

    /** @return array{employee_id: string, starts_at: string, ends_at: string, note?: ?string} */
    public function shift(): array
    {
        /** @var array{employee_id: string, starts_at: string, ends_at: string, note?: ?string} */
        return $this->validated();
    }
}
