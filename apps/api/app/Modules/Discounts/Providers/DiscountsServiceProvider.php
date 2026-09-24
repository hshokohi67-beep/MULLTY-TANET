<?php

namespace App\Modules\Discounts\Providers;

use App\Modules\Discounts\Contracts\CustomerTierLookup;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Support\NoCustomerTiers;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DiscountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(CustomerTierLookup::class, NoCustomerTiers::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::model('discount', Discount::class);

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
