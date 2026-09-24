<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\AvailabilityStatus;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AvailabilityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'status' => ['required', Rule::enum(AvailabilityStatus::class)],
            'sold_out_until' => ['nullable', 'date', 'after:now'],
        ];
    }
}
