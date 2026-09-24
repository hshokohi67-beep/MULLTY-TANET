<?php

namespace App\Modules\Core\Http\Requests;

use App\Support\Money\CurrencyUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'display_currency_unit' => ['sometimes', 'required', Rule::enum(CurrencyUnit::class)],
        ];
    }
}
