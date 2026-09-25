<?php

namespace App\Modules\Insights\Providers;

use App\Modules\Insights\Console\DailyReportCommand;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Management overview (live KPIs and alerts). Phase 11 adds aggregate tables behind the same API. */
final class InsightsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // The palette searches as you type (debounced); generous but bounded per user.
        RateLimiter::for('dashboard-search', fn (Request $request) => Limit::perMinute(90)->by('dashboard-search:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([DailyReportCommand::class]);
        }

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
