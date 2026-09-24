<?php

namespace App\Modules\Loyalty\Models;

use App\Modules\Customers\Models\Customer;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Points balance, lifetime spend and current tier of one customer.
 *
 * @property string $id
 * @property string $customer_id
 * @property int $points
 * @property int $lifetime_spend rial
 * @property ?string $tier_id
 * @property ?Carbon $tier_since
 * @property ?LoyaltyTier $tier
 * @property Customer $customer
 */
#[Fillable(['customer_id'])]
class LoyaltyAccount extends Model
{
    use BelongsToTenant, HasUlids;

    /** @var array<string, mixed> */
    protected $attributes = ['points' => 0, 'lifetime_spend' => 0];

    protected function casts(): array
    {
        return ['points' => 'integer', 'lifetime_spend' => 'integer', 'tier_since' => 'datetime'];
    }

    public static function for(Customer $customer): self
    {
        try {
            return self::query()->firstOrCreate(['customer_id' => $customer->id]);
        } catch (UniqueConstraintViolationException) {
            return self::query()->where('customer_id', $customer->id)->firstOrFail();
        }
    }

    /** @return BelongsTo<LoyaltyTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
