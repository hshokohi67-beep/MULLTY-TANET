<?php

namespace App\Modules\Advertising\Support;

use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdPlacement;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Price and availability of a placement for a date range. Amounts in rial; VAT uses the billing
 * rate and rounding (the invoice recomputes it the same way).
 */
final class AdPricing
{
    /**
     * The campaign's window in UTC: from the start of its first local day to the end of its last.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(string $startDate, int $days, string $timezone): array
    {
        $start = CarbonImmutable::parse($startDate, $timezone)->startOfDay();

        return [$start->utc(), $start->addDays($days)->utc()];
    }

    /**
     * Paid campaigns overlapping the window (the first to pay takes the slot). Capacity is
     * platform-wide, so this counts across cafés.
     */
    public static function taken(string $placement, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $exceptId = null): int
    {
        // Cross-tenant on purpose: a placement's capacity is shared by every café.
        return app(TenantContext::class)->bypass(fn () => AdCampaign::query()
            ->where('placement', $placement)->where('status', AdCampaign::PAID)
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)
            ->when($exceptId, fn ($q, $id) => $q->whereKeyNot($id))
            ->count());
    }

    /**
     * @return array{placement: string, start_date: string, days: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable, daily_price: int, subtotal: int, vat_rate: int, vat: int, total: int, capacity: int, taken: int, available: bool}
     */
    public static function quote(AdPlacement $placement, string $startDate, int $days, string $timezone, ?string $exceptId = null): array
    {
        [$startsAt, $endsAt] = self::window($startDate, $days, $timezone);
        $subtotal = $placement->daily_price * $days;
        $rate = (int) config('billing.vat_rate', 10);
        $vat = (int) round($subtotal * $rate / 100 / 10) * 10;
        $taken = self::taken($placement->key, $startsAt, $endsAt, $exceptId);

        return [
            'placement' => $placement->key, 'start_date' => $startDate, 'days' => $days, 'starts_at' => $startsAt, 'ends_at' => $endsAt,
            'daily_price' => $placement->daily_price, 'subtotal' => $subtotal, 'vat_rate' => $rate, 'vat' => $vat, 'total' => $subtotal + $vat,
            'capacity' => $placement->capacity, 'taken' => $taken, 'available' => $placement->is_active && $taken < $placement->capacity,
        ];
    }
}
