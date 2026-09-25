<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\SubscriptionState;
use App\Support\Localization\PersianNumber;

/** The notification-bell entry for the subscription: trial/period ending soon, grace, read-only. */
final class SubscriptionAlert
{
    /** @return array{type: string, severity: string, title: string, count: int, href: string}|null */
    public function current(): ?array
    {
        $snap = app(Entitlements::class)->snapshot();
        $s = $snap['subscription'];
        $end = $s->endsAt();
        $days = $end ? max(0, (int) ceil(now()->diffInHours($end) / 24)) : 0;
        $n = PersianNumber::toPersian((string) $days);
        $href = '/dashboard/billing';

        return match ($snap['state']) {
            SubscriptionState::ReadOnly => ['type' => 'subscription', 'severity' => 'danger', 'title' => 'اشتراک تمام شده و پنل فقط‌خواندنی است', 'count' => 1, 'href' => $href],
            SubscriptionState::Grace => ['type' => 'subscription', 'severity' => 'danger', 'title' => 'مهلت پرداخت اشتراک در جریان است؛ تمدید کنید', 'count' => 1, 'href' => $href],
            SubscriptionState::Trial => $days <= 5 ? ['type' => 'subscription', 'severity' => 'warning', 'title' => "دوره‌ی آزمایشی {$n} روز دیگر تمام می‌شود", 'count' => 1, 'href' => $href] : null,
            SubscriptionState::Active => $s->status === 'cancelled' || $days > 7 ? null : ['type' => 'subscription', 'severity' => 'warning', 'title' => "اشتراک {$n} روز دیگر تمام می‌شود", 'count' => 1, 'href' => $href],
        };
    }
}
