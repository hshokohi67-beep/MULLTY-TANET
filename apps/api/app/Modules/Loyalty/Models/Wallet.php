<?php

namespace App\Modules\Loyalty\Models;

use App\Modules\Customers\Models\Customer;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A customer's club wallet. The balance is only ever changed by PostWalletTransaction,
 * which keeps it equal to the sum of the ledger.
 *
 * @property string $id
 * @property string $customer_id
 * @property int $balance rial
 * @property Customer $customer
 */
#[Fillable(['customer_id'])]
class Wallet extends Model
{
    use BelongsToTenant, HasUlids;

    /** @var array<string, mixed> */
    protected $attributes = ['balance' => 0];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }

    public static function for(Customer $customer): self
    {
        try {
            return self::query()->firstOrCreate(['customer_id' => $customer->id]);
        } catch (UniqueConstraintViolationException) {
            return self::query()->where('customer_id', $customer->id)->firstOrFail();
        }
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
