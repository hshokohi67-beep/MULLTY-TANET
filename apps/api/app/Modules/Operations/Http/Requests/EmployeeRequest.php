<?php

namespace App\Modules\Operations\Http\Requests;

use App\Support\Tenancy\TenantContext;
use App\Support\Validation\IranianMobile;
use App\Support\Validation\NormalizesPersianInput;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EmployeeRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['rate', 'phone']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', new IranianMobile],
            'position' => ['nullable', 'string', 'max:60'],
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            // Membership of this café is checked by the controller (a domain error, not a field error).
            'user_id' => ['nullable', 'string', 'max:26',
                Rule::unique('employees', 'user_id')->where('tenant_id', app(TenantContext::class)->id())->ignore($this->route('employee'))],
            'pay_type' => ['required', Rule::in(['hourly', 'monthly'])],
            'rate' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'hired_on' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'نام', 'rate' => 'دستمزد', 'user_id' => 'حساب کاربری', 'phone' => 'موبایل', 'position' => 'سمت'];
    }
}
