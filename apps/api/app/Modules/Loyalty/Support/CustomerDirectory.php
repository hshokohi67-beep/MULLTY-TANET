<?php

namespace App\Modules\Loyalty\Support;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Customers\Models\Customer;
use App\Support\Localization\PersianNumber;
use App\Support\Localization\PersianTextNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * The staff customer list: customers with their wallet, points, tier and order stats in one query.
 */
final class CustomerDirectory
{
    /**
     * @param  array{q?: ?string, tier_id?: ?string, birth_month?: ?int}  $filters
     * @return Builder<Customer>
     */
    public static function query(array $filters = []): Builder
    {
        $completed = fn () => Order::query()->whereColumn('orders.customer_id', 'customers.id')->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected, OrderStatus::PendingPayment]);

        $query = Customer::query()
            ->leftJoin('wallets', fn ($j) => $j->on('wallets.customer_id', '=', 'customers.id')->on('wallets.tenant_id', '=', 'customers.tenant_id'))
            ->leftJoin('loyalty_accounts', fn ($j) => $j->on('loyalty_accounts.customer_id', '=', 'customers.id')->on('loyalty_accounts.tenant_id', '=', 'customers.tenant_id'))
            ->leftJoin('loyalty_tiers', fn ($j) => $j->on('loyalty_tiers.id', '=', 'loyalty_accounts.tier_id')->on('loyalty_tiers.tenant_id', '=', 'customers.tenant_id'))
            ->select('customers.*')
            ->addSelect([
                'wallets.balance as wallet_balance',
                'loyalty_accounts.points as points',
                'loyalty_accounts.lifetime_spend as lifetime_spend',
                'loyalty_accounts.tier_id as tier_id',
                'loyalty_tiers.name as tier_name',
                'loyalty_tiers.color as tier_color',
            ])
            ->selectSub($completed()->selectRaw('count(*)'), 'orders_count')
            ->selectSub($completed()->selectRaw('max(placed_at)'), 'last_order_at');

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $digits = ltrim((string) preg_replace('/\D/', '', PersianNumber::toLatin($term)), '0');
            $name = PersianTextNormalizer::forSearch($term);

            $query->where(function (Builder $q) use ($digits, $name): void {
                if ($name !== '') {
                    $q->where('customers.name', 'like', '%'.addcslashes($name, '%_\\').'%');
                }
                if (strlen($digits) >= 3) {
                    $q->orWhere('customers.phone_e164', 'like', '%'.$digits.'%');
                }
            });
        }

        if (! empty($filters['tier_id'])) {
            $query->where('loyalty_accounts.tier_id', $filters['tier_id']);
        }

        if (! empty($filters['birth_month'])) {
            $query->where('customers.birth_month', (int) $filters['birth_month']);
        }

        return $query;
    }
}
