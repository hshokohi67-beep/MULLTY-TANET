<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Commerce\Contracts\OnlinePaymentGate;
use App\Modules\Payments\Console\ReconcilePaymentsCommand;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\GatewayFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Not a singleton: the gate reads tenant settings at call time.
        $this->app->bind(OnlinePaymentGate::class, GatewayFactory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::model('payment', Payment::class);

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcilePaymentsCommand::class]);
        }
    }
}
