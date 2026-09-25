<?php

namespace App\Modules\Analytics\Providers;

use App\Modules\Analytics\Console\BackfillCommand;
use App\Modules\Analytics\Console\RollupCommand;
use App\Modules\Analytics\Support\DirtyDays;
use App\Modules\Commerce\Models\Order;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\OrderItemCost;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Operations\Models\AttendanceRecord;
use App\Modules\Operations\Models\Expense;
use App\Modules\Payments\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Aggregated metrics and reports. Sits above Commerce/Payments/Inventory/Operations: it listens to
 * their model events only to mark business days dirty, and reads their tables when rolling up.
 */
final class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');

        RateLimiter::for('report-export', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([RollupCommand::class, BackfillCommand::class]);
        }

        $orderDay = fn (?string $tenantId, ?string $orderId) => DirtyDays::mark($tenantId, $orderId === null ? null : Order::query()->whereKey($orderId)->value('business_date'));

        Order::saved(fn (Order $o) => DirtyDays::mark($o->tenant_id, $o->business_date));
        Payment::saved(fn (Payment $p) => $orderDay($p->tenant_id, $p->order_id));
        OrderItemCost::saved(fn (OrderItemCost $c) => $orderDay($c->tenant_id, $c->order_id));
        OrderItemCost::deleted(fn (OrderItemCost $c) => $orderDay($c->tenant_id, $c->order_id));

        $expense = function (Expense $e): void {
            DirtyDays::mark($e->tenant_id, $e->spent_on);
            $original = $e->getOriginal('spent_on');
            if ($original !== null && $e->wasChanged('spent_on')) {
                DirtyDays::mark($e->tenant_id, is_string($original) ? $original : $original->toDateString());
            }
        };
        Expense::saved($expense);
        Expense::deleted($expense);

        $attendance = function (AttendanceRecord $r): void {
            DirtyDays::markInstant($r->tenant_id, $r->clock_in_at);
            $original = $r->getOriginal('clock_in_at');
            if ($original !== null && $r->wasChanged('clock_in_at') && $original instanceof CarbonInterface) {
                DirtyDays::markInstant($r->tenant_id, $original);
            }
        };
        AttendanceRecord::saved($attendance);
        AttendanceRecord::deleted($attendance);

        StockMovement::created(function (StockMovement $m): void {
            if ($m->type === StockMovementType::Waste) {
                DirtyDays::markInstant($m->tenant_id, $m->created_at);
            }
        });
    }
}
