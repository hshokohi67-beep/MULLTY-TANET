<?php

namespace App\Modules\Discounts\Support;

use App\Modules\Discounts\Enums\DiscountKind;
use App\Modules\Discounts\Enums\DiscountRuleType;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountRule;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;

/**
 * Menu offers: automatic item discounts the café shows on its menu cards («نمایش روی منو»).
 *
 * A price on a card is a promise, so an offer may only depend on what the menu already knows:
 * the item, the branch and the clock. It is applied per item (every line gets its best offer),
 * before and on top of the order's one discount, so two advertised offers in one cart both hold.
 * The card price and the cart use the same per-unit formula (`unitPrice`).
 */
final class MenuOffers
{
    /**
     * Why a discount can't be a menu offer (empty = it can). Used by the request and the engine.
     *
     * @param  array<string, mixed>  $data  discount fields + `rules` keyed like SaveDiscount (product_ids…)
     * @return list<string>
     */
    public static function problems(array $data): array
    {
        $rules = is_array($data['rules'] ?? null) ? $data['rules'] : [];
        $problems = [];

        if (($data['code'] ?? null) !== null && $data['code'] !== '') {
            $problems[] = 'تخفیفی که روی منو نمایش داده می‌شود باید خودکار باشد (بدون کد).';
        }
        if (($data['applies_to'] ?? 'order') !== 'items' || (($rules['product_ids'] ?? []) === [] && ($rules['category_ids'] ?? []) === [])) {
            $problems[] = 'برای نمایش روی منو، تخفیف را روی «آیتم‌های مشخص» بگذارید و محصول یا دسته را انتخاب کنید.';
        }
        if ((int) ($data['min_order'] ?? 0) > 0 || ($data['max_discount'] ?? null) !== null) {
            $problems[] = 'تخفیف روی منو نمی‌تواند حداقل خرید یا سقف تخفیف داشته باشد؛ قیمت روی کارت باید برای همه قطعی باشد.';
        }
        if (($data['usage_limit'] ?? null) !== null || ($data['per_customer_limit'] ?? null) !== null) {
            $problems[] = 'تخفیف روی منو نمی‌تواند سقف دفعات استفاده داشته باشد.';
        }
        if (($rules['customer_ids'] ?? []) !== [] || ($rules['tier_ids'] ?? []) !== [] || ($rules['order_types'] ?? []) !== []) {
            $problems[] = 'تخفیف روی منو نمی‌تواند مخصوص مشتری، سطح باشگاه یا نوع سفارش باشد.';
        }

        return $problems;
    }

    /** A saved discount that works as a menu offer (rules loaded). */
    public static function qualifies(Discount $discount): bool
    {
        if (! $discount->show_on_menu) {
            return false;
        }

        $targets = fn (DiscountRuleType $type) => $discount->rules->filter(fn (DiscountRule $r) => $r->rule_type === $type)->pluck('target')->all();

        return self::problems([
            'code' => $discount->code,
            'applies_to' => $discount->applies_to,
            'min_order' => $discount->min_order,
            'max_discount' => $discount->max_discount,
            'usage_limit' => $discount->usage_limit,
            'per_customer_limit' => $discount->per_customer_limit,
            'rules' => [
                'product_ids' => $targets(DiscountRuleType::Product),
                'category_ids' => $targets(DiscountRuleType::Category),
                'customer_ids' => $targets(DiscountRuleType::Customer),
                'tier_ids' => $targets(DiscountRuleType::Tier),
                'order_types' => $targets(DiscountRuleType::OrderType),
            ],
        ]) === [];
    }

    /** The price of one unit after the offer (the web's `offerPrice` is the same formula). */
    public static function unitPrice(Discount $discount, int $unit): int
    {
        return max(0, $unit - self::unitAmount($discount, $unit));
    }

    /** Rial taken off one unit. */
    public static function unitAmount(Discount $discount, int $unit): int
    {
        return match ($discount->kind) {
            DiscountKind::Percent => min($unit, Money::rials($unit)->percentage($discount->value)->rials),
            DiscountKind::Fixed => min($unit, $discount->value),
        };
    }

    public static function appliesToBranch(Discount $discount, string $branchId): bool
    {
        $branches = $discount->rules->filter(fn (DiscountRule $r) => $r->rule_type === DiscountRuleType::Branch)->pluck('target')->all();

        return $branches === [] || in_array($branchId, $branches, true);
    }

    /**
     * @param  list<string>  $categoryIds
     */
    public static function appliesToItem(Discount $discount, string $productId, array $categoryIds): bool
    {
        foreach ($discount->rules as $rule) {
            if (($rule->rule_type === DiscountRuleType::Product && $rule->target === $productId)
                || ($rule->rule_type === DiscountRuleType::Category && in_array($rule->target, $categoryIds, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * When the offer is on, from shortly before `$now` for the next few days, as UTC instants
     * (null = open-ended). Mirrors DiscountEngine::activeAt: starts/ends, the weekday of the
     * moment, and a daily time window that may wrap past midnight. Touching windows are merged,
     * so a countdown always runs to the real end.
     *
     * @return list<array{from: ?string, to: ?string}>
     */
    public static function windows(Discount $discount, CarbonImmutable $now, int $days = 3): array
    {
        $schedule = $discount->schedule ?? [];
        $weekdays = array_map('intval', $schedule['weekdays'] ?? []);
        $from = $schedule['from'] ?? null;
        $to = $schedule['to'] ?? null;
        $starts = $discount->starts_at !== null ? CarbonImmutable::instance($discount->starts_at) : null;
        $ends = $discount->ends_at !== null ? CarbonImmutable::instance($discount->ends_at) : null;

        if ($ends !== null && $ends->lessThanOrEqualTo($now)) {
            return [];
        }

        if ($weekdays === [] && (empty($from) || empty($to))) {
            return [['from' => $starts?->utc()->toIso8601String(), 'to' => $ends?->utc()->toIso8601String()]];
        }

        $segments = [];
        for ($offset = -1; $offset <= $days; $offset++) {
            $day = $now->startOfDay()->addDays($offset);
            if ($weekdays !== [] && ! in_array($day->isoWeekday(), $weekdays, true)) {
                continue;
            }
            $next = $day->addDay();
            if (empty($from) || empty($to)) {
                $segments[] = [$day, $next];
            } elseif ($from <= $to) {
                $segments[] = [self::at($day, $from), self::at($day, $to)];
            } else {
                $segments[] = [$day, self::at($day, $to)];
                $segments[] = [self::at($day, $from), $next];
            }
        }

        $merged = [];
        foreach ($segments as [$a, $b]) {
            $a = $starts !== null && $starts->greaterThan($a) ? $starts : $a;
            $b = $ends !== null && $ends->lessThan($b) ? $ends : $b;
            if ($b->lessThanOrEqualTo($a) || $b->lessThanOrEqualTo($now)) {
                continue;
            }
            $last = array_key_last($merged);
            if ($last !== null && $merged[$last][1]->greaterThanOrEqualTo($a)) {
                $merged[$last][1] = $b->greaterThan($merged[$last][1]) ? $b : $merged[$last][1];
            } else {
                $merged[] = [$a, $b];
            }
        }

        return array_map(fn (array $w) => ['from' => $w[0]->utc()->toIso8601String(), 'to' => $w[1]->utc()->toIso8601String()], $merged);
    }

    /**
     * The branch's menu offers that are on now or later in the next days.
     *
     * @return list<array{id: string, label: string, kind: string, value: int, priority: int, product_ids: list<string>, category_ids: list<string>, windows: list<array{from: ?string, to: ?string}>}>
     */
    public static function forBranch(string $branchId, CarbonImmutable $now): array
    {
        $out = [];
        $offers = Discount::query()->with('rules')->whereNull('code')->where('is_active', true)->where('show_on_menu', true)->get();

        foreach ($offers as $discount) {
            if (! self::qualifies($discount) || ! self::appliesToBranch($discount, $branchId)) {
                continue;
            }
            $windows = self::windows($discount, $now);
            if ($windows === []) {
                continue;
            }
            $targets = fn (DiscountRuleType $type) => array_values($discount->rules->filter(fn (DiscountRule $r) => $r->rule_type === $type)->pluck('target')->all());
            $out[] = [
                'id' => $discount->id,
                'label' => $discount->name,
                'kind' => $discount->kind->value,
                'value' => $discount->value,
                'priority' => $discount->priority,
                'product_ids' => $targets(DiscountRuleType::Product),
                'category_ids' => $targets(DiscountRuleType::Category),
                'windows' => $windows,
            ];
        }

        return $out;
    }

    private static function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $day->setTime($h, $m);
    }
}
