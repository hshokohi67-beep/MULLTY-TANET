<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\PriceChangeLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PriceChangeLog */
final class PriceChangeLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'branch_id' => $this->branch_id,
            'old_amount' => $this->old_amount,
            'new_amount' => $this->new_amount,
            'reason' => $this->reason->value,
            'batch_id' => $this->batch_id,
            'actor_id' => $this->actor_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
