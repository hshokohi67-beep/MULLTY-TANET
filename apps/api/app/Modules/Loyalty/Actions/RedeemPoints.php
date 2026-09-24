<?php

namespace App\Modules\Loyalty\Actions;

use App\Modules\Core\Support\TenantSettings;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Enums\PointsTransactionType;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Exceptions\LoyaltyException;
use App\Modules\Loyalty\Models\WalletTransaction;
use App\Support\Localization\PersianNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Converts points into wallet credit at the tenant's point value. Both ledger rows are written
 * in one transaction, so points can't vanish without the credit (or the other way round).
 */
final class RedeemPoints
{
    public function __construct(
        private readonly PostPointsTransaction $points,
        private readonly PostWalletTransaction $wallet,
    ) {}

    public function handle(Customer $customer, int $points): WalletTransaction
    {
        if (! TenantSettings::get('loyalty.enabled')) {
            throw LoyaltyException::programDisabled();
        }

        $minimum = (int) TenantSettings::get('loyalty.min_redeem_points');
        if ($points < $minimum) {
            throw LoyaltyException::belowMinimumRedeem(PersianNumber::toPersian(number_format($minimum)));
        }

        $value = $points * (int) TenantSettings::get('loyalty.point_value');
        $ref = (string) Str::ulid();

        return DB::transaction(function () use ($customer, $points, $value, $ref): WalletTransaction {
            $label = PersianNumber::toPersian(number_format($points)).' امتیاز';
            $this->points->handle($customer, PointsTransactionType::Redeem, -$points, "redeem:{$ref}:points", 'تبدیل '.$label.' به کیف پول', actorType: 'customer', actorId: $customer->id);

            return $this->wallet->handle($customer, WalletTransactionType::PointsRedeem, $value, "redeem:{$ref}:wallet", 'تبدیل '.$label, actorType: 'customer', actorId: $customer->id);
        });
    }
}
