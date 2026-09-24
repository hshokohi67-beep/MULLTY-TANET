<?php

namespace App\Modules\Discounts\Http\Resources;

use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Discount */
final class DiscountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_automatic' => $this->isAutomatic(),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'value' => $this->value,
            'applies_to' => $this->applies_to,
            'min_order' => $this->min_order,
            'max_discount' => $this->max_discount,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'schedule' => $this->schedule,
            'usage_limit' => $this->usage_limit,
            'per_customer_limit' => $this->per_customer_limit,
            'used_count' => $this->used_count,
            'is_active' => $this->is_active,
            'priority' => $this->priority,
            'rules' => $this->whenLoaded('rules', fn () => $this->rules->map(fn (DiscountRule $r) => ['type' => $r->rule_type->value, 'target' => $r->target])->values()),
        ];
    }
}
