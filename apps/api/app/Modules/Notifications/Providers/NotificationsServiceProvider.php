<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Notifications\Console\VapidCommand;
use App\Modules\Notifications\Support\PushNotifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Browser (Web Push) notifications: customers following their order, staff hearing about new
 * ones. Sits above Commerce and only listens to its events.
 */
final class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');

        RateLimiter::for('push-subscribe', fn (Request $request) => Limit::perMinute(10)->by('push:'.$request->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([VapidCommand::class]);
        }

        Event::listen(OrderStatusChanged::class, fn (OrderStatusChanged $e) => app(PushNotifier::class)->orderStatus($e->order, $e->to));
        Event::listen(OrderPlaced::class, fn (OrderPlaced $e) => app(PushNotifier::class)->newOrder($e->order));
    }
}
