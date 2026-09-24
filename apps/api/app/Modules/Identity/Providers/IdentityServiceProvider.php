<?php

namespace App\Modules\Identity\Providers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Console\SyncPermissionsCommand;
use App\Modules\Identity\Models\PersonalAccessToken;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Identity\Support\PermissionResolver;
use App\Support\Tenancy\TenantBoundTokenable;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PermissionResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class]);
        }

        $this->configureTokens();
        $this->configureGates();
        $this->configureRateLimits();

        Route::model('member', TenantUser::class);
        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }

    private function configureTokens(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Customer tokens are bound to their tenant: they authenticate only when the request's
        // tenant context is that customer's tenant. Staff tokens are checked per tenant by membership.
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $tokenable = $token->tokenable;

            // Customers and kitchen devices live inside one tenant: their tokens only work there.
            if ($tokenable instanceof TenantBoundTokenable) {
                $tenantId = app(TenantContext::class)->id();

                return $tenantId !== null && $tenantId === $tokenable->tokenTenantId() && $tokenable->tokenIsActive();
            }

            return $tokenable instanceof User;
        });
    }

    private function configureGates(): void
    {
        // Every catalogue permission is a Gate ability, evaluated inside the current tenant.
        Gate::before(function ($actor, string $ability): ?bool {
            if (! PermissionCatalog::has($ability)) {
                return null;
            }

            $tenant = app(TenantContext::class)->tenant();

            if (! $actor instanceof User || $tenant === null) {
                return false;
            }

            return app(PermissionResolver::class)->allows($actor, $tenant, $ability);
        });
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('staff-login', fn (Request $request) => [
            Limit::perMinute(5)->by('staff-login:'.$request->ip().'|'.mb_strtolower((string) $request->input('identifier'))),
            Limit::perMinute(20)->by('staff-login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('otp-request', fn (Request $request) => [
            Limit::perMinute(5)->by('otp-request:'.$request->ip()),
            Limit::perHour(30)->by('otp-request-hour:'.$request->ip()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by('otp-verify:'.$request->ip()));
    }
}
