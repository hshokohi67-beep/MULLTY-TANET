<?php

namespace App\Modules\Loyalty\Http\Resources;

use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A wallet or points ledger row. `amount` is signed (rial for the wallet, points for points).
 *
 * @property WalletTransaction|LoyaltyTransaction $resource
 */
final class LedgerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id' => $row->id,
            'type' => $row->type->value,
            'type_label' => $row->type->label(),
            'amount' => $row instanceof WalletTransaction ? $row->amount : $row->points,
            'balance_after' => $row->balance_after,
            'description' => $row->description,
            'order_id' => $row->order_id,
            'actor_type' => $row->actor_type,
            'created_at' => $row->created_at->toIso8601String(),
        ];
    }
}
