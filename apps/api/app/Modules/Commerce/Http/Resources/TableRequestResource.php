<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Commerce\Models\TableSessionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TableSessionRequest */
final class TableRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status,
            'table' => $this->whenLoaded('table', fn () => ['id' => $this->table->id, 'label' => $this->table->label, 'branch_id' => $this->table->branch_id]),
            'created_at' => $this->created_at->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
        ];
    }
}
