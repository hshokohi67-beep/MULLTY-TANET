<?php

namespace App\Modules\Customers\Providers;

use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CustomersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::model('customer', Customer::class);

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
