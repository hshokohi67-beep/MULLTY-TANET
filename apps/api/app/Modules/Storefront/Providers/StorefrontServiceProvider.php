<?php

namespace App\Modules\Storefront\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Storefront marketing content (stories). Depends on Catalog and Core, never the other way. */
final class StorefrontServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        RateLimiter::for('story-events', fn (Request $request) => Limit::perMinute(60)->by('story-events:'.$request->ip()));

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
