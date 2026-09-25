<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Support\Tenancy\TenantContext;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IngredientRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['pack_size', 'low_stock_threshold', 'cost_per_big_unit']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $ingredient = $this->route('ingredient');

        return [
            'name' => ['required', 'string', 'max:120',
                Rule::unique('ingredients', 'name')->where('tenant_id', app(TenantContext::class)->id())->ignore($ingredient)],
            'unit' => [$ingredient ? 'sometimes' : 'required', Rule::in(['g', 'ml', 'pcs'])],
            'pack_label' => ['nullable', 'string', 'max:60', 'required_with:pack_size'],
            'pack_size' => ['nullable', 'numeric', 'gt:0', 'max:1000000', 'required_with:pack_label'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'cost_per_big_unit' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'نام', 'pack_size' => 'اندازه‌ی بسته', 'low_stock_threshold' => 'حد هشدار موجودی'];
    }
}
