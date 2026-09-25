<?php

namespace App\Modules\Operations\Http\Resources;

use App\Modules\Operations\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceRecord */
final class AttendanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee' => $this->whenLoaded('employee', fn () => ['id' => $this->employee->id, 'name' => $this->employee->name, 'position' => $this->employee->position]),
            'branch_id' => $this->branch_id,
            'shift' => $this->whenLoaded('shift', fn () => $this->shift ? ['id' => $this->shift->id, 'starts_at' => $this->shift->starts_at->toIso8601String(), 'ends_at' => $this->shift->ends_at->toIso8601String()] : null),
            'clock_in_at' => $this->clock_in_at->toIso8601String(),
            'clock_out_at' => $this->clock_out_at?->toIso8601String(),
            'minutes' => $this->minutes(),
            'late_minutes' => $this->relationLoaded('shift') ? $this->lateMinutes() : 0,
            'source' => $this->source,
            'note' => $this->note,
            'edited' => $this->edited_by !== null,
        ];
    }
}
