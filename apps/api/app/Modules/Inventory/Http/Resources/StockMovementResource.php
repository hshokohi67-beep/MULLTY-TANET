<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
final class StockMovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'ingredient' => $this->whenLoaded('ingredient', fn () => ['id' => $this->ingredient->id, 'name' => $this->ingredient->name, 'unit' => $this->ingredient->unit->value]),
            'branch_id' => $this->branch_id,
            'quantity' => (float) $this->quantity,
            'balance_after' => (float) $this->balance_after,
            'unit_cost' => $this->unit_cost,
            'order_id' => $this->order_id,
            'purchase_order_id' => $this->purchase_order_id,
            'note' => $this->note,
            'actor_type' => $this->actor_type,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
