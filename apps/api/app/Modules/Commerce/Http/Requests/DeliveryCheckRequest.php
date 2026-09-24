<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class DeliveryCheckRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'subtotal' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
