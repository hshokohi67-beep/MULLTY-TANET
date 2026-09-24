<?php

namespace App\Modules\Kitchen\Providers;

use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Kitchen\Console\ReleasePreordersCommand;
use App\Modules\Kitchen\Events\KitchenBoardChanged;
use App\Modules\Kitchen\Listeners\KitchenOrderListener;
use App\Modules\Kitchen\Models\KitchenDevice;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Support\Realtime\LiveVersion;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class KitchenServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ReleasePreordersCommand::class]);
        }

        Event::listen(OrderPlaced::class, [KitchenOrderListener::class, 'placed']);
        Event::listen(OrderStatusChanged::class, [KitchenOrderListener::class, 'statusChanged']);
        Event::listen(KitchenBoardChanged::class, fn (KitchenBoardChanged $e) => LiveVersion::bump(LiveVersion::kitchen($e->tenantId, $e->branchId)));
        KitchenItem::saved(fn (KitchenItem $item) => LiveVersion::bump(LiveVersion::kitchen($item->tenant_id, (string) Order::query()->whereKey($item->order_id)->value('branch_id'))));

        // Wrong pairing codes: 10 tries per 15 minutes per IP (a code has a million values and lives 10 minutes).
        RateLimiter::for('kds-pair', fn (Request $request) => Limit::perMinutes(15, 10)->by('kds-pair:'.$request->ip()));
        // Boards poll every few seconds from several screens behind one kitchen IP.
        RateLimiter::for('kds', fn (Request $request) => Limit::perMinute(240)->by('kds:'.($request->user('sanctum')?->getAuthIdentifier() ?? $request->ip())));

        // Realtime channel for when a broadcaster (Reverb) is configured: devices of that branch,
        // or staff of that tenant who may operate the KDS.
        Broadcast::channel('tenant.{tenantId}.kds.{branchId}', function ($user, string $tenantId, string $branchId): bool {
            if ($user instanceof KitchenDevice) {
                return $user->tokenIsActive() && $user->tenant_id === $tenantId && $user->branch_id === $branchId;
            }

            return $user instanceof User && app(TenantContext::class)->id() === $tenantId && Gate::forUser($user)->allows(PermissionCatalog::KDS_OPERATE);
        });

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
