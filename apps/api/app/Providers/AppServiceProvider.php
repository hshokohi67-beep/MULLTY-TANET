<?php

namespace App\Providers;

use App\Support\Entitlements\EntitlementGate;
use App\Support\Entitlements\PermissiveEntitlementGate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Plan entitlements: permissive until the Billing module binds the real (request-scoped) gate.
        $this->app->scoped(EntitlementGate::class, PermissiveEntitlementGate::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
