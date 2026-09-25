<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Commerce\Events\OrderPlaced;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Inventory\Listeners\InventoryOrderListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Ingredients, stock ledger, recipes, cost of goods and purchasing. Depends on Catalog/Commerce/Core. */
final class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Event::listen(OrderPlaced::class, [InventoryOrderListener::class, 'placed']);
        Event::listen(OrderStatusChanged::class, [InventoryOrderListener::class, 'statusChanged']);

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
