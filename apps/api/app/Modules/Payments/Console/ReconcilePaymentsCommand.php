<?php

namespace App\Modules\Payments\Console;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Tenant;
use App\Modules\Payments\Actions\ReconcilePayments;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Scheduled every minute (routes/console.php). */
final class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Verify stale online payments with the gateway and cancel orders whose payment window has passed';

    public function handle(TenantContext $context): int
    {
        // Cross-tenant read by design: only the IDs of tenants that have work to do.
        // Each tenant is then processed inside its own context.
        $tenantIds = $context->bypass(fn () => Payment::query()
            ->where('status', PaymentAttemptStatus::Pending)
            ->where('expires_at', '<=', now())
            ->distinct()->pluck('tenant_id')
            ->merge(Order::query()
                ->where('status', OrderStatus::PendingPayment)
                ->where('placed_at', '<=', now()->subMinutes((int) config('payments.order_payment_window_minutes')))
                ->distinct()->pluck('tenant_id'))
            ->unique()->values()->all());

        foreach (Tenant::query()->whereKey($tenantIds)->get() as $tenant) {
            $summary = $context->runAs($tenant, fn () => app(ReconcilePayments::class)->handle());
            $this->line(sprintf('%s: verified %d, expired %d, cancelled %d, errors %d', $tenant->slug, $summary['verified'], $summary['expired'], $summary['cancelled'], $summary['errors']));
        }

        return self::SUCCESS;
    }
}
