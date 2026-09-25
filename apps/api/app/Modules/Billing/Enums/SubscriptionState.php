<?php

namespace App\Modules\Billing\Enums;

use App\Modules\Billing\Models\Subscription;
use Carbon\CarbonInterface;

/**
 * The effective state, computed from the stored dates at the moment of asking (no cron flips it):
 * trial → (unpaid) grace for `billing.grace_days` → read-only; a paid period → grace → read-only;
 * a cancelled subscription runs to its period end, then read-only without grace.
 */
enum SubscriptionState: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case ReadOnly = 'read_only';

    public static function of(Subscription $subscription, CarbonInterface $now): self
    {
        $grace = (int) config('billing.grace_days', 7);
        $end = $subscription->endsAt();

        if ($end === null) {
            return self::ReadOnly;
        }
        if ($now->lt($end)) {
            return $subscription->status === 'trialing' ? self::Trial : self::Active;
        }
        if ($subscription->status !== 'cancelled' && $now->lt($end->copy()->addDays($grace))) {
            return self::Grace;
        }

        return self::ReadOnly;
    }

    public function writable(): bool
    {
        return $this !== self::ReadOnly;
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'دوره‌ی آزمایشی',
            self::Active => 'فعال',
            self::Grace => 'مهلت پرداخت',
            self::ReadOnly => 'فقط‌خواندنی',
        };
    }
}
