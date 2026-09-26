<?php

namespace App\Modules\Messaging\Providers;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Events\OrderStatusChanged;
use App\Modules\Messaging\Console\PruneSmsLogsCommand;
use App\Modules\Messaging\Console\SmsCampaignsCommand;
use App\Modules\Messaging\Jobs\SendOrderSms;
use App\Modules\Messaging\Support\CafeSmsMessenger;
use App\Support\Sms\CafeMessenger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The café's own SMS: its panel, automatic messages and campaigns. Sits above Commerce, Customers
 * and Loyalty; lower modules reach it only through the CafeMessenger contract.
 */
final class MessagingServiceProvider extends ServiceProvider
{
    /** Order types whose customer is waiting elsewhere for "ready" (not seated at a table). */
    private const READY_TYPES = [OrderType::Takeaway, OrderType::Online, OrderType::Phone];

    public function register(): void
    {
        $this->app->bind(CafeMessenger::class, CafeSmsMessenger::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::prefix('api/v1')->middleware('api')->name('api.')->group(__DIR__.'/../routes.php');

        RateLimiter::for('sms-test', fn (Request $request) => Limit::perMinute(3)->by('sms-test:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([SmsCampaignsCommand::class, PruneSmsLogsCommand::class]);
        }

        Event::listen(OrderStatusChanged::class, function (OrderStatusChanged $e): void {
            $template = match (true) {
                $e->to === OrderStatus::Ready && in_array($e->order->type, self::READY_TYPES, true) => 'order_ready',
                $e->to === OrderStatus::OutForDelivery => 'order_sent',
                default => null,
            };
            if ($template !== null && $e->order->customer_id !== null) {
                SendOrderSms::dispatch((string) $e->order->tenant_id, (string) $e->order->id, $template);
            }
        });
    }
}
