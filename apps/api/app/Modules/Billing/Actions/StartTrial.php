<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;

/** Gives the current tenant its trial subscription (the trial plan, `billing.trial_days`). Idempotent. */
final class StartTrial
{
    public function handle(): Subscription
    {
        $existing = Subscription::query()->first();
        if ($existing !== null) {
            return $existing;
        }

        $plan = Plan::query()->where('is_trial_plan', true)->orderBy('sort')->first()
            ?? Plan::query()->orderByDesc('monthly_price')->firstOrFail();

        return Subscription::query()->create([
            'plan_id' => $plan->id,
            'cycle' => 'monthly',
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
        ]);
    }
}
