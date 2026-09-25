<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionAddon;
use App\Modules\Billing\Support\Entitlements;
use App\Modules\Core\Enums\TenantStatus;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Turns a paid invoice into service: plan, cycle and add-ons from the invoice; a renewal extends
 * from the current end, anything else starts now. Call inside a transaction with the invoice
 * locked and just marked paid (so it runs once per invoice).
 */
final class ApplyPaidInvoice
{
    public function handle(BillingInvoice $invoice): Subscription
    {
        $subscription = Subscription::query()->lockForUpdate()->firstOrFail();
        $now = CarbonImmutable::now();

        $end = $subscription->current_period_end ? CarbonImmutable::instance($subscription->current_period_end) : null;
        $start = $invoice->mode === 'renew' && $end !== null && $subscription->status !== 'trialing' ? $end : $now;
        $periodEnd = $invoice->cycle === 'yearly' ? $start->addYearNoOverflow() : $start->addMonthNoOverflow();

        $renewing = $start->equalTo($end ?? $now) && $invoice->mode === 'renew';
        $subscription->update([
            'plan_id' => $invoice->plan_id,
            'cycle' => $invoice->cycle,
            'status' => 'active',
            // A renewal extends the running period; anything else starts a new one today.
            'current_period_start' => $renewing && $subscription->current_period_start ? $subscription->current_period_start : $start,
            'current_period_end' => $periodEnd,
            'scheduled_plan_id' => null,
            'scheduled_cycle' => null,
            'scheduled_addons' => null,
            'cancelled_at' => null,
            'reminder_stage' => null,
        ]);

        SubscriptionAddon::query()->where('subscription_id', $subscription->id)->delete();
        foreach ($invoice->addons as $item) {
            SubscriptionAddon::query()->create(['subscription_id' => $subscription->id, 'addon_id' => $item['addon_id'], 'quantity' => $item['quantity']]);
        }

        $invoice->update(['period_start' => $start, 'period_end' => $periodEnd]);

        $tenant = app(TenantContext::class)->require();
        if ($tenant->status === TenantStatus::Trial) {
            $tenant->update(['status' => TenantStatus::Active]);
        }

        app(Entitlements::class)->forget();

        return $subscription->refresh();
    }
}
