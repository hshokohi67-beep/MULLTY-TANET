<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class DeliveryZoneRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $zone = $this->route('zone');

        return [
            'branch_id' => [$zone ? 'prohibited' : 'required', 'string', TenantExists::in('branches')],
            'name' => ['required', 'string', 'max:80'],
            'radius_m' => ['required', 'integer', 'between:100,50000'],
            'delivery_fee' => ['required', 'integer', 'min:0', 'max:100000000'],
            'free_delivery_min' => ['nullable', 'integer', 'min:0', 'max:10000000000'],
            'min_order' => ['nullable', 'integer', 'min:0', 'max:10000000000'],
            'eta_minutes' => ['nullable', 'integer', 'between:5,300'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
        ];
    }
}
