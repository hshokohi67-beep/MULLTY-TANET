<?php

namespace App\Modules\Messaging\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Tenant;
use App\Support\Localization\PersianNumber;
use App\Support\Sms\CafeMessenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * «سفارش آماده است» / «سفارش ارسال شد» to the order's customer through the café's own line.
 * Queued after commit: the provider call never runs inside the order transaction.
 */
final class SendOrderSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public function __construct(public readonly string $tenantId, public readonly string $orderId, public readonly string $template)
    {
        $this->afterCommit = true;
    }

    public function handle(TenantContext $context, CafeMessenger $sms): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }
        $context->runAs($tenant, function () use ($sms, $tenant): void {
            $order = Order::query()->with('customer')->find($this->orderId);
            $phone = $order?->customer?->phone_e164;
            if ($order === null || $phone === null) {
                return;
            }
            $sms->template($this->template, $phone, [
                'name' => $order->customer->name ?: 'مشتری',
                'number' => PersianNumber::toPersian((string) $order->daily_number),
                'cafe' => $tenant->name,
            ]);
        });
    }
}
