<?php

namespace App\Modules\Operations\Http\Requests;

use App\Modules\Operations\Models\Expense;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\NormalizesPersianInput;
use App\Support\Validation\TenantExists;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExpenseRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['amount']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Not in the future (a day of slack for timezone edges).
        $latest = CarbonImmutable::now(app(TenantContext::class)->require()->timezone)->addDay()->toDateString();

        return [
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'category_id' => ['required', 'string', TenantExists::in('expense_categories')],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$latest],
            'method' => ['required', Rule::in(array_keys(Expense::METHODS))],
            'payee' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['amount' => 'مبلغ', 'category_id' => 'دسته', 'spent_on' => 'تاریخ', 'payee' => 'دریافت‌کننده'];
    }
}
