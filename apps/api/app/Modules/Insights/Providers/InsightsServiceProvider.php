<?php

namespace App\Modules\Insights\Providers;

use App\Modules\Insights\Console\DailyReportCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Management overview (live KPIs and alerts). Phase 11 adds aggregate tables behind the same API. */
final class InsightsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([DailyReportCommand::class]);
        }

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
