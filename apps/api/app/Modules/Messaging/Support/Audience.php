<?php

namespace App\Modules\Messaging\Support;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Customers\Models\Customer;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a marketing campaign reaches: always and only customers who opted in to marketing
 * messages, optionally narrowed by club tier, birth month, "not seen for N days" or
 * "ordered at least once". Runs inside the café's tenant context.
 */
final class Audience
{
    /**
     * @param  array{tier_id?: ?string, birth_month?: ?int, inactive_days?: ?int, has_ordered?: bool}  $filter
     * @return Builder<Customer>
     */
    public static function query(array $filter): Builder
    {
        $query = Customer::query()->where('marketing_opt_in', true)->whereNotNull('phone_e164');

        if (! empty($filter['tier_id'])) {
            $query->whereIn('id', LoyaltyAccount::query()->where('tier_id', $filter['tier_id'])->select('customer_id'));
        }
        if (! empty($filter['birth_month'])) {
            $query->where('birth_month', (int) $filter['birth_month']);
        }
        if (! empty($filter['has_ordered']) || ! empty($filter['inactive_days'])) {
            $query->whereIn('id', Order::query()->whereNotNull('customer_id')->where('status', OrderStatus::Completed)->select('customer_id'));
        }
        if (! empty($filter['inactive_days'])) {
            $since = CarbonImmutable::now()->subDays((int) $filter['inactive_days']);
            // Regulars who haven't been back since: no completed order after the cut-off.
            $query->whereNotIn('id', Order::query()->whereNotNull('customer_id')->where('status', OrderStatus::Completed)->where('created_at', '>=', $since)->select('customer_id'));
        }

        return $query;
    }

    /** @param  array{tier_id?: ?string, birth_month?: ?int, inactive_days?: ?int, has_ordered?: bool}  $filter */
    public static function count(array $filter): int
    {
        return self::query($filter)->count();
    }
}
