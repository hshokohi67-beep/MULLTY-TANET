<?php

namespace App\Modules\Operations\Http\Resources;

use App\Modules\Operations\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Shift */
final class ShiftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'branch_id' => $this->branch_id,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'minutes' => (int) $this->starts_at->diffInMinutes($this->ends_at),
            'note' => $this->note,
        ];
    }
}
