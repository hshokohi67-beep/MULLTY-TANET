<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Console\ImportWooCommerceCatalogCommand;
use App\Modules\Catalog\Contracts\MenuInsights;
use App\Modules\Catalog\Contracts\MenuOfferFeed;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\MenuTag;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\NoMenuInsights;
use App\Modules\Catalog\Support\NoMenuOffers;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(MenuInsights::class, NoMenuInsights::class);
        $this->app->bindIf(MenuOfferFeed::class, NoMenuOffers::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportWooCommerceCatalogCommand::class]);
        }

        Route::model('category', Category::class);
        Route::model('product', Product::class);
        Route::model('modifierGroup', ModifierGroup::class);
        Route::model('menuTag', MenuTag::class);

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');
    }
}
