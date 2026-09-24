<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class ModifierGroupRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'min_select' => ['required', 'integer', 'between:0,20'],
            'max_select' => ['required', 'integer', 'between:0,20'],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
            'modifiers' => ['present', 'array', 'max:30'],
            'modifiers.*.id' => ['nullable', 'string', TenantExists::in('modifiers')],
            'modifiers.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.price_delta' => ['required', 'integer', 'between:-100000000000,100000000000'],
            'modifiers.*.is_default' => ['sometimes', 'boolean'],
            'modifiers.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
