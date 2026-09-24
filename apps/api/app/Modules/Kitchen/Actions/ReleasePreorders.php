<?php

namespace App\Modules\Kitchen\Actions;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Support\TenantSettings;
use App\Support\Tenancy\TenantContext;
use Throwable;

/**
 * Pre-orders reach the kitchen `preorder.release_minutes` before their slot instead of when they
 * are placed, so tomorrow's order doesn't sit on today's board. Runs every minute; idempotent
 * (routing skips orders that already have kitchen items).
 */
final class ReleasePreorders
{
    /** The widest release window a tenant can set (settings allow 0–240 minutes). */
    private const MAX_WINDOW_MINUTES = 240;

    public static function isWaiting(Order $order): bool
    {
        return $order->scheduled_for !== null
            && $order->scheduled_for->copy()->subMinutes((int) TenantSettings::get('preorder.release_minutes'))->isFuture();
    }

    /** @return int orders routed */
    public function handle(): int
    {
        $context = app(TenantContext::class);

        // Cross-tenant on purpose: find which tenants have pre-orders due soon, then act inside each.
        $tenantIds = $context->bypass(fn () => Order::query()
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now()->addMinutes(self::MAX_WINDOW_MINUTES))
            ->where('scheduled_for', '>=', now()->subDay())
            ->whereIn('status', [OrderStatus::Placed, OrderStatus::Accepted])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('kitchen_items')->whereColumn('kitchen_items.order_id', 'orders.id'))
            ->distinct()->pluck('tenant_id'));

        $routed = 0;
        foreach (Tenant::query()->whereIn('id', $tenantIds)->get() as $tenant) {
            $routed += $context->runAs($tenant, function (): int {
                $due = now()->addMinutes((int) TenantSettings::get('preorder.release_minutes'));
                $count = 0;

                Order::query()->whereNotNull('scheduled_for')->where('scheduled_for', '<=', $due)
                    ->whereIn('status', [OrderStatus::Placed, OrderStatus::Accepted])
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('kitchen_items')->whereColumn('kitchen_items.order_id', 'orders.id'))
                    ->each(function (Order $order) use (&$count): void {
                        try {
                            $count += app(RouteOrderToKitchen::class)->handle($order) > 0 ? 1 : 0;
                        } catch (Throwable $e) {
                            report($e); // one bad order must not stop the others
                        }
                    });

                return $count;
            });
        }

        return $routed;
    }
}
