<?php

namespace App\Modules\Operations\Http\Resources;

use App\Modules\Operations\Models\Employee;
use App\Support\Localization\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
final class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone_e164 ? PhoneNormalizer::toLocal($this->phone_e164) : null,
            'position' => $this->position,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'pay_type' => $this->pay_type,
            'rate' => $this->rate,
            'hourly_rate' => (int) round($this->hourlyRate()),
            'hired_on' => $this->hired_on?->toDateString(),
            'is_active' => $this->is_active,
        ];
    }
}
