<?php

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Actions\StartTrial;
use App\Modules\Billing\Console\RenewalsCommand;
use App\Modules\Billing\Models\EntitlementOverride;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionAddon;
use App\Modules\Billing\Support\Entitlements;
use App\Modules\Billing\Support\InvoiceFulfillers;
use App\Modules\Core\Models\Tenant;
use App\Support\Entitlements\EntitlementGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Plans, subscriptions, invoices and the entitlement gate every module asks. The gate is
 * request-scoped (Octane-safe) and replaces the permissive default from AppServiceProvider.
 */
final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request, reachable both as the concrete class and as the gate.
        $this->app->scoped(Entitlements::class);
        $this->app->scoped(EntitlementGate::class, fn ($app) => $app->make(Entitlements::class));
        // Registrations only (kind => class name): safe to keep for the process lifetime.
        $this->app->singleton(InvoiceFulfillers::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');

        RateLimiter::for('billing-checkout', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([RenewalsCommand::class]);
        }

        // Every new café starts on the trial plan.
        Tenant::created(fn (Tenant $tenant) => app(TenantContext::class)->runAs($tenant, fn () => app(StartTrial::class)->handle()));

        // Any change to what a café is entitled to invalidates this request's memo.
        $forget = fn () => app(Entitlements::class)->forget();
        // Scoped instances are only flushed by queues/Octane; clear the memo after every request too.
        $this->app->terminating($forget);
        foreach ([Subscription::class, SubscriptionAddon::class, EntitlementOverride::class] as $model) {
            $model::saved($forget);
            $model::deleted($forget);
        }
    }
}
