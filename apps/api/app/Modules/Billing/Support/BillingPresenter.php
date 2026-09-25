<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\SubscriptionState;
use App\Modules\Billing\Models\Addon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use Carbon\CarbonImmutable;

/** API shapes for billing (amounts in rial, instants as UTC ISO-8601). */
final class BillingPresenter
{
    /** @return array<string, mixed> */
    public static function plan(Plan $plan): array
    {
        return [
            'id' => $plan->id, 'key' => $plan->key, 'name' => $plan->name, 'tagline' => $plan->tagline,
            'monthly_price' => $plan->monthly_price, 'yearly_price' => $plan->yearly_price,
            'features' => $plan->features, 'is_public' => $plan->is_public, 'is_trial_plan' => $plan->is_trial_plan,
        ];
    }

    /** @return array<string, mixed> */
    public static function addon(Addon $addon): array
    {
        return [
            'id' => $addon->id, 'key' => $addon->key, 'name' => $addon->name, 'description' => $addon->description,
            'monthly_price' => $addon->monthly_price, 'yearly_price' => $addon->price('yearly'), 'grants' => $addon->grants, 'plans' => $addon->plans,
        ];
    }

    /**
     * @param  array<string, bool|int|null>  $features
     * @return array<string, mixed>
     */
    public static function subscription(Subscription $s, SubscriptionState $state, array $features): array
    {
        $end = $s->endsAt();
        $grace = (int) config('billing.grace_days', 7);
        $now = CarbonImmutable::now();

        return [
            'plan' => self::plan($s->plan),
            'cycle' => $s->cycle,
            'status' => $s->status,
            'state' => $state->value,
            'state_label' => $state->label(),
            'trial_ends_at' => $s->trial_ends_at?->toIso8601String(),
            'current_period_start' => $s->current_period_start?->toIso8601String(),
            'current_period_end' => $s->current_period_end?->toIso8601String(),
            'ends_at' => $end?->toIso8601String(),
            'grace_ends_at' => $end && $s->status !== 'cancelled' ? $end->copy()->addDays($grace)->toIso8601String() : null,
            // Days until the next change of state (end of trial/period, or end of grace).
            'days_left' => match ($state) {
                SubscriptionState::Trial, SubscriptionState::Active => $end ? max(0, (int) ceil($now->diffInHours($end) / 24)) : null,
                SubscriptionState::Grace => $end ? max(0, (int) ceil($now->diffInHours($end->copy()->addDays($grace)) / 24)) : null,
                SubscriptionState::ReadOnly => 0,
            },
            'cancelled_at' => $s->cancelled_at?->toIso8601String(),
            'scheduled' => $s->scheduledPlan ? ['plan' => self::plan($s->scheduledPlan), 'cycle' => $s->scheduled_cycle ?? $s->cycle] : null,
            'addons' => $s->addons->map(fn ($a) => ['id' => $a->addon_id, 'key' => $a->addon->key, 'name' => $a->addon->name, 'quantity' => $a->quantity])->values()->all(),
            'features' => $features,
        ];
    }

    /** @return array<string, mixed> */
    public static function invoice(BillingInvoice $i): array
    {
        return [
            'id' => $i->id, 'number' => $i->number, 'kind' => $i->kind, 'status' => $i->status,
            'plan' => ['id' => $i->plan->id, 'name' => $i->plan->name], 'cycle' => $i->cycle, 'mode' => $i->mode,
            'lines' => $i->lines, 'subtotal' => $i->subtotal, 'credit' => $i->credit, 'vat_rate' => $i->vat_rate, 'vat' => $i->vat, 'total' => $i->total,
            'period_start' => $i->period_start?->toIso8601String(), 'period_end' => $i->period_end?->toIso8601String(),
            'due_at' => $i->due_at?->toIso8601String(), 'paid_at' => $i->paid_at?->toIso8601String(),
            'paid_via' => $i->paid_via, 'reference' => $i->reference, 'created_at' => $i->created_at->toIso8601String(),
        ];
    }

    /**
     * @param  array{mode: string, plan: Plan, cycle: string, addons: list<array{addon_id: string, quantity: int}>, lines: list<array{label: string, amount: int}>, subtotal: int, credit: int, vat_rate: int, vat: int, total: int, period_start: ?CarbonImmutable, period_end: ?CarbonImmutable, warnings: list<string>}  $q
     * @return array<string, mixed>
     */
    public static function quote(array $q): array
    {
        return [
            'mode' => $q['mode'], 'plan' => self::plan($q['plan']), 'cycle' => $q['cycle'], 'addons' => $q['addons'],
            'lines' => $q['lines'], 'subtotal' => $q['subtotal'], 'credit' => $q['credit'], 'vat_rate' => $q['vat_rate'], 'vat' => $q['vat'], 'total' => $q['total'],
            'period_start' => $q['period_start']?->toIso8601String(), 'period_end' => $q['period_end']?->toIso8601String(),
            'warnings' => $q['warnings'],
        ];
    }
}
