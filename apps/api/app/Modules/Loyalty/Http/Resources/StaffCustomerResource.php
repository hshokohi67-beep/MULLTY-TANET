<?php

namespace App\Modules\Loyalty\Http\Resources;

use App\Modules\Customers\Models\Customer;
use App\Support\Localization\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * A customer as staff see them in lists. Club columns come from the list query
 * (wallet_balance, points, lifetime_spend, tier_*, orders_count, last_order_at).
 *
 * @mixin Customer
 */
final class StaffCustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $a = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => PhoneNormalizer::toLocal($this->phone_e164),
            'birth_month' => $this->birth_month,
            'birth_day' => $this->birth_day,
            'wallet_balance' => (int) ($a['wallet_balance'] ?? 0),
            'points' => (int) ($a['points'] ?? 0),
            'lifetime_spend' => (int) ($a['lifetime_spend'] ?? 0),
            'tier' => isset($a['tier_id']) ? ['id' => $a['tier_id'], 'name' => $a['tier_name'] ?? null, 'color' => $a['tier_color'] ?? null] : null,
            'orders_count' => (int) ($a['orders_count'] ?? 0),
            'last_order_at' => isset($a['last_order_at']) ? Carbon::parse((string) $a['last_order_at'], 'UTC')->toIso8601String() : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
