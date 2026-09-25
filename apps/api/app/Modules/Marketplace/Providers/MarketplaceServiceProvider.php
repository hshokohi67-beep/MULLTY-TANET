<?php

namespace App\Modules\Marketplace\Providers;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionAddon;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\TenantBranding;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Marketplace\Console\RefreshCommand;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Support\MarketplaceSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The public marketplace. Sits above Core/Catalog/Commerce/Billing: it only listens to their model
 * events to know which café's public facts changed, and re-projects that café when the request ends.
 */
final class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MarketplaceSync::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([RefreshCommand::class]);
        }

        $touch = fn (Model $m) => app(MarketplaceSync::class)->touch((string) ($m instanceof Tenant ? $m->getKey() : $m->getAttribute('tenant_id')));
        $models = [
            MarketplaceListing::class, TenantBranding::class, Branch::class, BranchOpeningHour::class, Product::class, ProductImage::class,
            ProductPrice::class, Discount::class, DeliveryZone::class, RestaurantTable::class, Subscription::class, SubscriptionAddon::class, Tenant::class,
        ];
        foreach ($models as $model) {
            $model::saved($touch);
            $model::deleted($touch);
        }
        $this->app->terminating(fn () => app(MarketplaceSync::class)->flush());
    }
}
