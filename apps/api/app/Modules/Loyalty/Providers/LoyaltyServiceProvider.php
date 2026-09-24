<?php

namespace App\Modules\Loyalty\Providers;

use App\Modules\Commerce\Events\OrderCompleted;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Discounts\Contracts\CustomerTierLookup;
use App\Modules\Loyalty\Console\BirthdayGiftsCommand;
use App\Modules\Loyalty\Listeners\ClubOrderListener;
use App\Modules\Loyalty\Support\LoyaltyTierLookup;
use App\Modules\Payments\Events\PaymentRefunded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerTierLookup::class, LoyaltyTierLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Event::listen(OrderCompleted::class, [ClubOrderListener::class, 'completed']);
        Event::listen(OrderStatusChanged::class, [ClubOrderListener::class, 'statusChanged']);
        Event::listen(PaymentRefunded::class, [ClubOrderListener::class, 'refunded']);

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([BirthdayGiftsCommand::class]);
        }
    }
}
