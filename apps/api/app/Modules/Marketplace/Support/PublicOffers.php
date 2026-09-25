<?php

namespace App\Modules\Marketplace\Support;

use App\Modules\Discounts\Enums\DiscountKind;
use App\Modules\Discounts\Enums\DiscountRuleType;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountRule;
use App\Support\Localization\PersianNumber;

/**
 * The café's current offers as the public may see them: automatic discounts only (never codes),
 * active now, not used up, and not limited to particular customers or club tiers. Labels only —
 * no amounts used, limits or internal ids.
 */
final class PublicOffers
{
    /** @return array<string, list<string>> branch id (or "*" for every branch) => labels */
    public static function byBranch(): array
    {
        $now = now();
        $discounts = Discount::query()->with('rules')
            ->where('is_active', true)->whereNull('code')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->orderByDesc('priority')->limit(20)->get();

        $out = [];
        foreach ($discounts as $d) {
            $types = $d->rules->map(fn (DiscountRule $r) => $r->rule_type)->all();
            if (in_array(DiscountRuleType::Customer, $types, true) || in_array(DiscountRuleType::Tier, $types, true)) {
                continue; // personal or members-only: not a public offer
            }
            $branches = $d->rules->filter(fn (DiscountRule $r) => $r->rule_type === DiscountRuleType::Branch)->pluck('target')->all();
            $label = self::label($d);
            foreach ($branches === [] ? ['*'] : $branches as $branch) {
                $out[(string) $branch][] = $label;
            }
        }

        return $out;
    }

    public static function label(Discount $d): string
    {
        $fa = fn (int $n) => PersianNumber::toPersian(number_format($n, 0, '.', '٬'));
        $text = $d->kind === DiscountKind::Percent
            ? '٪'.PersianNumber::toPersian((string) round($d->value / 100, 1)).' تخفیف'
            : $fa(intdiv($d->value, 10)).' تومان تخفیف';
        if ($d->applies_to === 'items') {
            $text .= ' روی برخی آیتم‌ها';
        }
        if ($d->min_order > 0) {
            $text .= ' برای خرید بالای '.$fa(intdiv($d->min_order, 10)).' تومان';
        }
        if (! empty($d->schedule)) {
            $text .= ' (در ساعات مشخص)';
        }

        return $text;
    }
}
