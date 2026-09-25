<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\SubscriptionState;
use App\Modules\Billing\Models\Addon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\QuoteBuilder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantUser;
use App\Support\Localization\PersianNumber;
use App\Support\Sms\SmsMessage;
use App\Support\Sms\SmsProvider;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Daily, per tenant: issues the renewal invoice a week before a paid period ends (with any
 * scheduled plan change) and texts the owner at 7 days, 1 day and when the panel turns read-only.
 * Each reminder stage is sent once (`reminder_stage`).
 */
final class RunRenewals
{
    private const RENEWAL_DAYS = 7;

    public function __construct(private readonly ManageBilling $billing, private readonly SmsProvider $sms) {}

    /** @return array{invoice: bool, reminder: ?string} */
    public function handle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $subscription = Subscription::query()->with(['plan', 'addons.addon'])->first();
        if ($subscription === null) {
            return ['invoice' => false, 'reminder' => null];
        }

        $invoice = false;
        $end = $subscription->endsAt();
        if ($subscription->status === 'active' && $end !== null && $now->lt($end) && $now->diffInDays($end) <= self::RENEWAL_DAYS
            && ! BillingInvoice::query()->where('kind', 'renewal')->where('status', 'open')->exists()
            && ! BillingInvoice::query()->where('status', 'paid')->where('period_start', '>=', $end)->exists()) {
            $plan = $subscription->scheduledPlan ?? $subscription->plan;
            $addons = $subscription->scheduled_addons ?? QuoteBuilder::selection($subscription);
            $valid = Addon::query()->whereIn('id', array_column($addons, 'addon_id'))->get()->filter(fn (Addon $a) => $a->availableFor($plan))->keyBy('id');
            $quantities = [];
            foreach ($addons as $a) {
                if ($valid->has($a['addon_id'])) {
                    $quantities[$a['addon_id']] = $a['quantity'];
                }
            }
            $quote = QuoteBuilder::build($subscription, $plan, $subscription->scheduled_cycle ?? $subscription->cycle, $quantities, $now, renewal: true);
            $this->billing->createInvoice($quote, 'renewal', null, $end);
            $invoice = true;
        }

        return ['invoice' => $invoice, 'reminder' => $this->remind($subscription, $now)];
    }

    private function remind(Subscription $subscription, CarbonImmutable $now): ?string
    {
        $state = SubscriptionState::of($subscription, $now);
        $end = $subscription->endsAt();
        $tenant = app(TenantContext::class)->require();

        $stage = match (true) {
            $state === SubscriptionState::ReadOnly => 'readonly',
            $subscription->status === 'cancelled' || $end === null || $state === SubscriptionState::Grace => null,
            $now->diffInHours($end) <= 30 => 'd1',
            $now->diffInDays($end) <= 7 => 'd7',
            default => null,
        };
        if ($stage === null || $subscription->reminder_stage === $stage) {
            return null;
        }

        $what = $subscription->status === 'trialing' ? 'دوره‌ی آزمایشی' : 'اشتراک';
        $text = match ($stage) {
            'readonly' => "{$what} {$tenant->name} تمام شد و پنل فقط‌خواندنی است. برای ادامه، از بخش «اشتراک» پنل تمدید کنید.",
            'd1' => "{$what} {$tenant->name} فردا تمام می‌شود. برای تمدید به بخش «اشتراک» پنل بروید.",
            default => "{$what} {$tenant->name} ".PersianNumber::toPersian((string) max(1, (int) ceil($now->diffInHours($end) / 24))).' روز دیگر تمام می‌شود. برای تمدید به بخش «اشتراک» پنل بروید.',
        };

        $phones = TenantUser::query()->whereHas('roles', fn ($q) => $q->where('key', Role::OWNER))->with('user:id,phone_e164')->get()
            ->map(fn (TenantUser $m) => $m->user->phone_e164)->filter()->values()->all();
        if ($phones !== []) {
            $this->sms->send(new SmsMessage(array_values(array_map('strval', $phones)), $text));
        }
        $subscription->update(['reminder_stage' => $stage, 'reminded_at' => $now]);

        return $stage;
    }
}
