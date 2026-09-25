<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Addon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Support\Entitlements\FeatureLabels;
use App\Support\Localization\PersianNumber;
use Carbon\CarbonImmutable;

/**
 * Prices a plan/cycle/add-on selection against the current subscription:
 *  - renew: the same selection, extending from the current end (no credit);
 *  - scheduled: a cheaper selection while a paid period runs — applied at the period end, nothing to pay;
 *  - pay_now: starts today; the unused part of a running paid period is credited (pro rata, whole toman).
 * VAT is charged on (subtotal − credit). Amounts in rial.
 */
final class QuoteBuilder
{
    /**
     * @param  array<string, int>  $addonQuantities  addon id => quantity
     * @param  bool  $renewal  the renewal invoice: always extends from the current end
     * @return array{mode: string, plan: Plan, cycle: string, addons: list<array{addon_id: string, quantity: int}>, lines: list<array{label: string, amount: int}>, subtotal: int, credit: int, vat_rate: int, vat: int, total: int, period_start: ?CarbonImmutable, period_end: ?CarbonImmutable, warnings: list<string>}
     */
    public static function build(Subscription $subscription, Plan $plan, string $cycle, array $addonQuantities, CarbonImmutable $now, bool $renewal = false): array
    {
        $addons = Addon::query()->whereIn('id', array_keys($addonQuantities))->get()->keyBy('id');
        $cycleLabel = $cycle === 'yearly' ? 'سالانه' : 'ماهانه';
        $lines = [['label' => "پلن {$plan->name} • {$cycleLabel}", 'amount' => $plan->price($cycle)]];
        $selected = [];
        foreach ($addonQuantities as $id => $quantity) {
            $addon = $addons->get($id);
            if ($addon === null || ! $addon->is_public) {
                throw BillingException::planUnavailable();
            }
            if (! $addon->availableFor($plan)) {
                throw BillingException::addonUnavailable($addon->name);
            }
            $selected[] = ['addon_id' => $addon->id, 'quantity' => $quantity];
            $lines[] = ['label' => $addon->name.($quantity > 1 ? ' × '.PersianNumber::toPersian((string) $quantity) : '')." • {$cycleLabel}", 'amount' => $addon->price($cycle) * $quantity];
        }
        usort($selected, fn (array $a, array $b) => strcmp($a['addon_id'], $b['addon_id']));
        $subtotal = array_sum(array_column($lines, 'amount'));

        $end = $subscription->current_period_end ? CarbonImmutable::instance($subscription->current_period_end) : null;
        $paidRunning = $subscription->status !== 'trialing' && $end !== null && $now->lt($end);
        $current = self::selection($subscription);
        $same = $subscription->plan_id === $plan->id && $subscription->cycle === $cycle && $current === $selected;
        $graceEnd = $end?->addDays((int) config('billing.grace_days', 7));

        $mode = 'pay_now';
        $start = $now;
        if ($renewal && $end !== null) {
            // The renewal invoice (possibly for a scheduled plan) continues from the current end.
            $mode = 'renew';
            $start = $end;
        } elseif ($same && $subscription->status !== 'trialing' && $end !== null && $graceEnd !== null && $now->lt($graceEnd)) {
            // Paying during grace still starts from the old end: the grace days were used.
            $mode = 'renew';
            $start = $end;
        } elseif ($paidRunning && $subscription->status === 'active' && self::monthly($plan, $cycle, $selected, $addons->all()) < self::currentMonthly($subscription)) {
            $mode = 'scheduled';
        }

        $credit = 0;
        if ($mode === 'pay_now' && $paidRunning) {
            // The unused time at the cycle's daily rate (an early renewal can make the period longer than one cycle).
            $total = ($subscription->cycle === 'yearly' ? 365 : 30) * 86400;
            // Only time that was paid for earns credit (not days the platform gifted with an extension).
            $paidUntil = BillingInvoice::query()->where('status', 'paid')->max('period_end');
            $coveredEnd = $paidUntil === null ? $now : $end->min(CarbonImmutable::parse((string) $paidUntil));
            $left = max(0, $coveredEnd->getTimestamp() - $now->getTimestamp());
            $paid = $subscription->plan->price($subscription->cycle)
                + (int) $subscription->addons->sum(fn ($a) => $a->addon->price($subscription->cycle) * $a->quantity);
            $credit = min($subtotal, intdiv((int) floor($paid * $left / $total), 10) * 10);
        }

        $rate = (int) config('billing.vat_rate', 10);
        $vat = $mode === 'scheduled' ? 0 : (int) round(($subtotal - $credit) * $rate / 100 / 10) * 10;
        $periodEnd = $mode === 'scheduled' ? null : ($cycle === 'yearly' ? $start->addYearNoOverflow() : $start->addMonthNoOverflow());

        return [
            'mode' => $mode,
            'plan' => $plan,
            'cycle' => $cycle,
            'addons' => $selected,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'credit' => $credit,
            'vat_rate' => $rate,
            'vat' => $vat,
            'total' => $mode === 'scheduled' ? 0 : $subtotal - $credit + $vat,
            'period_start' => $mode === 'scheduled' ? $end : $start,
            'period_end' => $periodEnd,
            'warnings' => self::warnings($subscription, $plan, $selected, $addons->all()),
        ];
    }

    /** @return list<array{addon_id: string, quantity: int}> */
    public static function selection(Subscription $subscription): array
    {
        $current = $subscription->addons->map(fn ($a) => ['addon_id' => $a->addon_id, 'quantity' => $a->quantity])->values()->all();
        usort($current, fn (array $a, array $b) => strcmp($a['addon_id'], $b['addon_id']));

        return $current;
    }

    /**
     * @param  list<array{addon_id: string, quantity: int}>  $selected
     * @param  array<string, Addon>  $addons
     */
    private static function monthly(Plan $plan, string $cycle, array $selected, array $addons): float
    {
        $months = $cycle === 'yearly' ? 12 : 1;
        $sum = $plan->price($cycle);
        foreach ($selected as $s) {
            $sum += $addons[$s['addon_id']]->price($cycle) * $s['quantity'];
        }

        return $sum / $months;
    }

    private static function currentMonthly(Subscription $subscription): float
    {
        $months = $subscription->cycle === 'yearly' ? 12 : 1;

        return ($subscription->plan->price($subscription->cycle)
            + (int) $subscription->addons->sum(fn ($a) => $a->addon->price($subscription->cycle) * $a->quantity)) / $months;
    }

    /**
     * What a smaller selection would mean, in plain words. Nothing is ever deleted.
     *
     * @param  list<array{addon_id: string, quantity: int}>  $selected
     * @param  array<string, Addon>  $addons
     * @return list<string>
     */
    private static function warnings(Subscription $subscription, Plan $plan, array $selected, array $addons): array
    {
        $next = [];
        foreach ([...FeatureCatalog::SWITCHES, ...FeatureCatalog::LIMITS] as $key) {
            $value = $plan->features[$key] ?? null;
            $next[$key] = FeatureCatalog::isSwitch($key) ? $value === true : (is_int($value) ? $value : null);
        }
        foreach ($selected as $s) {
            foreach ($addons[$s['addon_id']]->grants as $key => $grant) {
                if ($grant === true) {
                    $next[$key] = true;
                } elseif (is_int($grant) && is_int($next[$key] ?? null)) {
                    $next[$key] += $grant * $s['quantity'];
                }
            }
        }

        $now = Entitlements::effective($subscription);
        $warnings = [];
        $off = array_values(array_filter(FeatureCatalog::SWITCHES, fn (string $key) => ($now[$key] ?? false) === true && $next[$key] === false));
        if ($off !== []) {
            $names = implode('، ', array_map(fn (string $key) => '«'.FeatureLabels::of($key).'»', $off));
            $warnings[] = (count($off) === 1 ? "{$names} خاموش می‌شود" : "این امکانات خاموش می‌شوند: {$names}").'؛ اطلاعاتشان حذف نمی‌شود و با ارتقای دوباره برمی‌گردد.';
        }
        foreach (['branches', 'staff', 'products'] as $key) {
            $used = Usage::count($key);
            if (is_int($next[$key]) && $used > $next[$key]) {
                $warnings[] = 'الان '.PersianNumber::toPersian((string) $used).' '.FeatureLabels::of($key).' دارید و این پلن '.PersianNumber::toPersian((string) $next[$key])
                    .' اجازه می‌دهد؛ چیزی حذف نمی‌شود، ولی تا کمتر نشود مورد جدید نمی‌توانید اضافه کنید.';
            }
        }

        return $warnings;
    }
}
