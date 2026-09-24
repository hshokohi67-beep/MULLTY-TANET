<?php

namespace App\Modules\Discounts\Support;

use App\Modules\Discounts\Enums\DiscountKind;
use App\Modules\Discounts\Enums\DiscountRuleType;
use App\Modules\Discounts\Exceptions\CouponException;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Discounts\Models\DiscountUsage;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Database\Eloquent\Collection;

/**
 * The single place discounts are decided (never in controllers).
 *
 * V1 policy: one discount per order, either the customer's coupon or else the automatic
 * discount worth the most (ties → higher priority). No stacking. Other policies
 * (stacking, loyalty-tier discounts, BOGO) are extension points behind this class.
 */
final class DiscountEngine
{
    /** @throws CouponException when a coupon was given but cannot be used */
    public function apply(DiscountContext $context): ?AppliedDiscount
    {
        if ($context->couponCode !== null && trim($context->couponCode) !== '') {
            return $this->applyCoupon($context, mb_strtoupper(trim($context->couponCode)));
        }

        return $this->bestAutomatic($context);
    }

    private function applyCoupon(DiscountContext $context, string $code): AppliedDiscount
    {
        $discount = Discount::query()->with('rules')->where('code', $code)->where('is_active', true)->first()
            ?? throw CouponException::notFound();

        if (! $this->activeAt($discount, $context)) {
            throw CouponException::notActiveNow();
        }

        if ($discount->usage_limit !== null && $discount->used_count >= $discount->usage_limit) {
            throw CouponException::exhausted();
        }

        if ($discount->per_customer_limit !== null) {
            if ($context->customerId === null) {
                throw CouponException::loginRequired();
            }

            if ($this->customerUses($discount, $context->customerId) >= $discount->per_customer_limit) {
                throw CouponException::alreadyUsed();
            }
        }

        if ($context->subtotal < $discount->min_order) {
            throw CouponException::minimumOrder(MoneyFormatter::format(Money::rials($discount->min_order)));
        }

        return $this->calculate($discount, $context) ?? throw CouponException::notApplicable();
    }

    private function bestAutomatic(DiscountContext $context): ?AppliedDiscount
    {
        /** @var Collection<int, Discount> $candidates */
        $candidates = Discount::query()->with('rules')->whereNull('code')->where('is_active', true)->get();
        $best = null;

        foreach ($candidates as $discount) {
            if (! $this->activeAt($discount, $context) || $context->subtotal < $discount->min_order) {
                continue;
            }

            if ($discount->usage_limit !== null && $discount->used_count >= $discount->usage_limit) {
                continue;
            }

            if ($discount->per_customer_limit !== null
                && ($context->customerId === null || $this->customerUses($discount, $context->customerId) >= $discount->per_customer_limit)) {
                continue;
            }

            $applied = $this->calculate($discount, $context);

            if ($applied === null) {
                continue;
            }

            if ($best === null
                || $applied->amount > $best->amount
                || ($applied->amount === $best->amount && $discount->priority > $best->discount->priority)) {
                $best = $applied;
            }
        }

        return $best;
    }

    /** Rules + amount. Null when the discount doesn't apply to this order at all. */
    private function calculate(Discount $discount, DiscountContext $context): ?AppliedDiscount
    {
        $rules = $discount->rules->groupBy(fn (DiscountRule $r) => $r->rule_type->value);
        $targets = fn (DiscountRuleType $type): array => $rules->get($type->value)?->pluck('target')->all() ?? [];

        foreach ([
            DiscountRuleType::Branch->value => $context->branchId,
            DiscountRuleType::OrderType->value => $context->orderType,
            DiscountRuleType::Customer->value => $context->customerId,
            DiscountRuleType::Tier->value => $context->customerTierId,
        ] as $type => $value) {
            $allowed = $targets(DiscountRuleType::from($type));

            if ($allowed !== [] && ! in_array($value, $allowed, true)) {
                return null;
            }
        }

        $productTargets = $targets(DiscountRuleType::Product);
        $categoryTargets = $targets(DiscountRuleType::Category);
        $restrictsItems = $productTargets !== [] || $categoryTargets !== [];

        $eligibleLines = array_filter($context->lines, fn (array $line) => ! $restrictsItems
            || in_array($line['product_id'], $productTargets, true)
            || array_intersect($line['category_ids'], $categoryTargets) !== []);

        if ($eligibleLines === []) {
            return null;
        }

        $base = $discount->applies_to === 'items'
            ? array_sum(array_column($eligibleLines, 'line_total'))
            : $context->subtotal;

        $amount = match ($discount->kind) {
            DiscountKind::Percent => Money::rials($base)->percentage($discount->value)->rials,
            DiscountKind::Fixed => $discount->value,
        };

        if ($discount->max_discount !== null) {
            $amount = min($amount, $discount->max_discount);
        }

        $amount = max(0, min($amount, $base));

        return $amount > 0 ? new AppliedDiscount($discount, $amount, $base) : null;
    }

    private function activeAt(Discount $discount, DiscountContext $context): bool
    {
        $now = $context->now;

        if ($discount->starts_at !== null && $now->lessThan($discount->starts_at)) {
            return false;
        }

        if ($discount->ends_at !== null && $now->greaterThan($discount->ends_at)) {
            return false;
        }

        $schedule = $discount->schedule ?? [];

        if (! empty($schedule['weekdays']) && ! in_array($now->isoWeekday(), array_map('intval', $schedule['weekdays']), true)) {
            return false;
        }

        if (! empty($schedule['from']) && ! empty($schedule['to'])) {
            $time = $now->format('H:i');
            $from = $schedule['from'];
            $to = $schedule['to'];

            // A window like 22:00–02:00 spans midnight.
            $inside = $from <= $to ? ($time >= $from && $time < $to) : ($time >= $from || $time < $to);

            if (! $inside) {
                return false;
            }
        }

        return true;
    }

    private function customerUses(Discount $discount, string $customerId): int
    {
        return DiscountUsage::query()->where('discount_id', $discount->getKey())->where('customer_id', $customerId)->count();
    }
}
