<?php

namespace App\Modules\Insights\Support;

use App\Modules\Catalog\Enums\AvailabilityStatus;
use App\Modules\Catalog\Models\ProductAvailability;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\TableSessionRequest;
use App\Modules\Kitchen\Models\KitchenDevice;
use App\Modules\Loyalty\Models\Wallet;
use App\Support\Localization\PersianNumber;
use Closure;

/**
 * "What needs my attention?" Each alert says what, how many, how serious, and where to fix it.
 * Only alerts the viewer is allowed to act on are built.
 */
final class Alerts
{
    /**
     * @param  Closure(string): bool  $can
     * @return list<array{type: string, severity: string, title: string, count: int, href: string}>
     */
    public static function for(?string $branchId, Closure $can): array
    {
        $alerts = [];
        $n = fn (int $count) => PersianNumber::toPersian((string) $count);
        $orders = fn () => Order::query()->when($branchId, fn ($q, $id) => $q->where('branch_id', $id));

        if ($can('orders.view')) {
            // A pre-order is only late relative to its slot, not to when it was placed.
            $late = $orders()->whereIn('status', [OrderStatus::Placed, OrderStatus::Accepted, OrderStatus::Preparing])
                ->where('placed_at', '<', now()->subMinutes(15))
                ->where(fn ($q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<', now()->subMinutes(15)))
                ->count();
            if ($late > 0) {
                $alerts[] = ['type' => 'late_orders', 'severity' => 'danger', 'title' => "{$n($late)} سفارش بیش از ۱۵ دقیقه منتظر مانده", 'count' => $late, 'href' => '/dashboard/orders'];
            }

            $upcoming = $orders()->whereIn('status', [OrderStatus::Placed, OrderStatus::Accepted])
                ->whereBetween('scheduled_for', [now(), now()->addHour()])->count();
            if ($upcoming > 0) {
                $alerts[] = ['type' => 'upcoming_preorders', 'severity' => 'info', 'title' => "{$n($upcoming)} پیش‌سفارش برای یک ساعت آینده", 'count' => $upcoming, 'href' => '/dashboard/orders'];
            }

            $calls = TableSessionRequest::query()->where('status', 'open')->when($branchId, fn ($q, $id) => $q->whereHas('table', fn ($t) => $t->where('branch_id', $id)))->count();
            if ($calls > 0) {
                $alerts[] = ['type' => 'table_calls', 'severity' => 'warning', 'title' => "{$n($calls)} میز منتظر گارسون یا صورت‌حساب است", 'count' => $calls, 'href' => '/dashboard/orders'];
            }

            $stuck = $orders()->where('status', OrderStatus::PendingPayment)->where('placed_at', '<', now()->subMinutes(10))->count();
            if ($stuck > 0) {
                $alerts[] = ['type' => 'pending_payment', 'severity' => 'info', 'title' => "{$n($stuck)} سفارش آنلاین هنوز پرداخت نشده", 'count' => $stuck, 'href' => '/dashboard/orders/history?status=pending_payment&range=7d'];
            }
        }

        if ($can('payments.view')) {
            $refund = $orders()->whereColumn('paid_total', '>', 'refunded_total')
                ->where(fn ($q) => $q->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])->orWhereRaw('paid_total - refunded_total > total'))
                ->count();
            if ($refund > 0) {
                $alerts[] = ['type' => 'needs_refund', 'severity' => 'danger', 'title' => "{$n($refund)} سفارش منتظر بازگشت وجه به مشتری", 'count' => $refund, 'href' => '/dashboard/orders/history?status=cancelled%2Crejected&range=30d'];
            }
        }

        if ($can('catalog.view')) {
            $soldOut = ProductAvailability::query()->where('status', AvailabilityStatus::SoldOut)
                ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
                ->where(fn ($q) => $q->whereNull('sold_out_until')->orWhere('sold_out_until', '>', now()))
                ->distinct()->count('product_id');
            if ($soldOut > 0) {
                $alerts[] = ['type' => 'sold_out', 'severity' => 'warning', 'title' => "{$n($soldOut)} آیتم منو «تمام شد» است", 'count' => $soldOut, 'href' => '/dashboard/menu'];
            }
        }

        if ($can('kds.manage')) {
            $offline = KitchenDevice::query()->whereNull('revoked_at')->whereNotNull('paired_at')
                ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
                ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes(10)))->count();
            if ($offline > 0) {
                $alerts[] = ['type' => 'kds_offline', 'severity' => 'info', 'title' => "{$n($offline)} نمایشگر آشپزخانه بیش از ۱۰ دقیقه آفلاین است", 'count' => $offline, 'href' => '/dashboard/kitchen'];
            }
        }

        if ($can('customers.view')) {
            $negative = Wallet::query()->where('balance', '<', 0)->count();
            if ($negative > 0) {
                $alerts[] = ['type' => 'negative_wallets', 'severity' => 'info', 'title' => "کیف پول {$n($negative)} مشتری پس از برگشت کش‌بک منفی است", 'count' => $negative, 'href' => '/dashboard/customers'];
            }
        }

        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($alerts, fn (array $a, array $b) => $rank[$a['severity']] <=> $rank[$b['severity']]);

        return $alerts;
    }
}
