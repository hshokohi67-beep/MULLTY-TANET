<?php

namespace App\Modules\Loyalty\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * What a completed order earned (computed once, with the rules of that moment) and how much of it
 * the ledgers currently hold. Refunds lower the targets; the difference is posted as reversals.
 *
 * @property string $id
 * @property string $order_id
 * @property string $customer_id
 * @property int $base_amount
 * @property int $points_full
 * @property int $cashback_full
 * @property int $points_posted
 * @property int $cashback_posted
 * @property int $spend_posted
 * @property int $version
 * @property ?array<string, mixed> $snapshot
 */
#[Fillable(['order_id', 'customer_id', 'base_amount', 'points_full', 'cashback_full', 'points_posted', 'cashback_posted', 'spend_posted', 'version', 'snapshot'])]
class LoyaltyOrderAward extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'base_amount' => 'integer',
            'points_full' => 'integer',
            'cashback_full' => 'integer',
            'points_posted' => 'integer',
            'cashback_posted' => 'integer',
            'spend_posted' => 'integer',
            'version' => 'integer',
            'snapshot' => 'array',
        ];
    }
}
