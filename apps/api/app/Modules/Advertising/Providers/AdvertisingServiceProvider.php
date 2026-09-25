<?php

namespace App\Modules\Advertising\Providers;

use App\Modules\Advertising\Actions\FulfilAdInvoice;
use App\Modules\Advertising\Actions\ProjectCampaign;
use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Support\AdServing;
use App\Modules\Advertising\Support\CampaignInvoices;
use App\Modules\Billing\Support\InvoiceFulfillers;
use App\Modules\Core\Models\Tenant;
use App\Modules\Marketplace\Contracts\SponsoredContent;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Paid placements in «خوراک‌گردی». Sits above Marketplace and Billing: it answers Marketplace's
 * SponsoredContent contract and fulfils Billing invoices of kind `ad`.
 */
final class AdvertisingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SponsoredContent::class, AdServing::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');

        RateLimiter::for('ad-events', fn (Request $request) => Limit::perMinute(90)->by('ad-events:'.$request->ip()));

        $this->app->make(InvoiceFulfillers::class)->register(CampaignInvoices::KIND, FulfilAdInvoice::class);

        // The public slot follows the campaign (paid ⇄ suspended, dates, creative).
        AdCampaign::saved(fn (AdCampaign $c) => app(ProjectCampaign::class)->handle($c));
        AdCampaign::deleted(fn (AdCampaign $c) => app(ProjectCampaign::class)->handle($c->setAttribute('status', AdCampaign::CANCELLED)));
        // A new café slug moves its slots along.
        Tenant::saved(function (Tenant $tenant): void {
            if ($tenant->wasChanged('slug')) {
                app(TenantContext::class)->runAs($tenant, fn () => AdCampaign::query()->where('status', AdCampaign::PAID)->get()->each(fn (AdCampaign $c) => app(ProjectCampaign::class)->handle($c)));
            }
        });
    }
}
