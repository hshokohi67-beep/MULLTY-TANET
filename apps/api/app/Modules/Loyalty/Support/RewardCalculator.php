<?php

namespace App\Modules\Loyalty\Support;

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Loyalty\Models\CashbackRule;
use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Pure-ish reward maths for one order. Amounts are integer rial.
 */
final class RewardCalculator
{
    /**
     * The part of the order paid with "real" money that is still kept: counts counter payments that
     * were never recorded (a completed order was paid), excludes the wallet (no reward loops), and
     * subtracts refunds.
     */
    public function rewardableAmount(Order $order): int
    {
        $payments = Payment::query()->where('order_id', $order->id)->where('status', PaymentAttemptStatus::Paid)->get(['method', 'amount', 'refunded_amount']);
        $wallet = $payments->where('method', PaymentMethod::Wallet);

        $walletPaid = (int) $wallet->sum('amount');
        $walletRefunded = (int) $wallet->sum('refunded_amount');
        $paid = (int) $payments->sum('amount');
        $refunded = (int) $payments->sum('refunded_amount');

        $otherPaid = $paid - $walletPaid;
        $unrecorded = max(0, $order->total - $paid);
        $otherRefunded = $refunded - $walletRefunded;

        return max(0, min($order->total, $otherPaid + $unrecorded - $otherRefunded));
    }

    public function points(int $base, ?LoyaltyTier $tier): int
    {
        $rate = (int) TenantSettings::get('loyalty.points_per_100k');
        $multiplier = $tier->points_multiplier ?? 10_000;

        return intdiv(intdiv($base, 100_000) * $rate * $multiplier, 10_000);
    }

    /**
     * Sum of every matching active cashback rule. A category rule counts the spend on products in
     * that category or its subcategories; the order discount is spread over lines proportionally.
     * The spend is scaled to the rewardable share, so wallet-paid money earns nothing.
     *
     * @return array{total: int, rules: list<array{id: string, name: string, spend: int, reward: int}>}
     */
    public function cashback(Order $order, int $base): array
    {
        $rules = CashbackRule::query()->where('is_active', true)->get();

        if ($rules->isEmpty() || $base <= 0 || $order->subtotal <= 0) {
            return ['total' => 0, 'rules' => []];
        }

        $share = fn (int $amount): int => intdiv($amount * $base, max(1, $order->subtotal));
        $lineCategories = $this->lineCategories($order);
        $applied = [];

        foreach ($rules as $rule) {
            $spend = $rule->category_id === null
                ? $base
                : $share((int) $order->items->filter(fn (OrderItem $i) => in_array($rule->category_id, $lineCategories[$i->id] ?? [], true))->sum('line_total'));

            $reward = $rule->rewardFor(min($spend, $base));

            if ($reward > 0) {
                $applied[] = ['id' => $rule->id, 'name' => $rule->name, 'spend' => $spend, 'reward' => $reward];
            }
        }

        return ['total' => array_sum(array_column($applied, 'reward')), 'rules' => $applied];
    }

    /**
     * Category ids (including ancestors) of each order line's product.
     *
     * @return array<string, list<string>>
     */
    private function lineCategories(Order $order): array
    {
        $order->loadMissing('items');
        $productIds = $order->items->pluck('product_id')->unique()->all();

        $direct = DB::table('product_categories')
            ->where('tenant_id', $order->tenant_id)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'category_id'])
            ->groupBy('product_id');

        /** @var array<string, ?string> $parents */
        $parents = Category::query()->pluck('parent_id', 'id')->all();
        $withAncestors = function (string $id) use ($parents): array {
            $ids = [];
            for ($depth = 0; $id !== null && $depth < 10; $depth++) {
                $ids[] = $id;
                $id = $parents[$id] ?? null;
            }

            return $ids;
        };

        $result = [];
        foreach ($order->items as $item) {
            $ids = [];
            foreach ($direct->get($item->product_id, collect()) as $row) {
                array_push($ids, ...$withAncestors((string) $row->category_id));
            }
            $result[$item->id] = array_values(array_unique($ids));
        }

        return $result;
    }
}
