<?php

namespace App\Modules\Commerce\Providers;

use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderSession;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Commerce\Models\TableSessionRequest;
use App\Support\Realtime\LiveVersion;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CommerceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::model('table', RestaurantTable::class);
        Route::model('zone', DeliveryZone::class);
        Route::model('order', Order::class);

        // Live boards poll a version number instead of re-reading everything (see LiveVersion).
        Order::saved(fn (Order $order) => LiveVersion::bump(LiveVersion::orders($order->tenant_id), LiveVersion::kitchen($order->tenant_id, $order->branch_id)));
        OrderSession::saved(fn (OrderSession $session) => LiveVersion::bump(LiveVersion::orders($session->tenant_id), LiveVersion::kitchen($session->tenant_id, $session->branch_id)));
        TableSessionRequest::saved(function (TableSessionRequest $request): void {
            $branchId = RestaurantTable::query()->whereKey($request->table_id)->value('branch_id');
            LiveVersion::bump(LiveVersion::orders($request->tenant_id), LiveVersion::kitchen($request->tenant_id, (string) $branchId));
        });

        RateLimiter::for('storefront', fn (Request $request) => Limit::perMinute(120)->by('storefront:'.$request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinute(10)->by('checkout:'.$request->ip()),
            Limit::perMinute(5)->by('checkout-cart:'.sha1((string) $request->header('X-Cart-Token'))),
        ]);
        RateLimiter::for('delivery-check', fn (Request $request) => Limit::perMinute(30)->by('delivery-check:'.$request->ip()));
        RateLimiter::for('table-requests', fn (Request $request) => Limit::perMinute(10)->by('table-req:'.$request->ip()));

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
