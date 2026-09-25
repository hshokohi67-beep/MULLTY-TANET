<?php

namespace App\Modules\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The tenant's single subscription. Only `status` is stored (trialing | active | cancelled); the
 * effective state (trial, active, grace, read-only) is computed from the dates by `SubscriptionState`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $plan_id
 * @property string $cycle
 * @property string $status
 * @property ?Carbon $trial_ends_at
 * @property ?Carbon $current_period_start
 * @property ?Carbon $current_period_end
 * @property ?string $scheduled_plan_id
 * @property ?string $scheduled_cycle
 * @property ?list<array{addon_id: string, quantity: int}> $scheduled_addons
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $reminded_at
 * @property ?string $reminder_stage
 * @property Plan $plan
 * @property ?Plan $scheduledPlan
 * @property Collection<int, SubscriptionAddon> $addons
 */
#[Fillable(['plan_id', 'cycle', 'status', 'trial_ends_at', 'current_period_start', 'current_period_end', 'scheduled_plan_id', 'scheduled_cycle', 'scheduled_addons', 'cancelled_at', 'reminded_at', 'reminder_stage'])]
class Subscription extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime', 'current_period_start' => 'datetime', 'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime', 'reminded_at' => 'datetime', 'scheduled_addons' => 'array',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'scheduled_plan_id');
    }

    /** @return HasMany<SubscriptionAddon, $this> */
    public function addons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class);
    }

    /** When access ends if nothing is paid: the trial end or the period end. */
    public function endsAt(): ?Carbon
    {
        return $this->status === 'trialing' ? $this->trial_ends_at : $this->current_period_end;
    }
}
