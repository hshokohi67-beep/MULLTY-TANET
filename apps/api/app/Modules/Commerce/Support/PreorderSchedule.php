<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Support\OpeningHoursEvaluator;
use App\Modules\Core\Support\TenantSettings;
use App\Support\Localization\JalaliDate;
use Carbon\CarbonImmutable;

/**
 * Pre-order time slots of one branch: opening hours cut into fixed slots (tenant-local), from
 * now + lead time to the end of the last allowed day, with per-slot capacity. The same rules
 * validate a chosen time at checkout, so the picker and the server never disagree.
 */
final class PreorderSchedule
{
    private ?OpeningHoursEvaluator $hours;

    private function __construct(
        private readonly Branch $branch,
        private readonly string $timezone,
        private readonly CarbonImmutable $now,
        public readonly int $leadMinutes,
        public readonly int $maxDays,
        public readonly int $slotMinutes,
        public readonly int $capacity,
    ) {
        $intervals = BranchOpeningHour::query()->where('branch_id', $branch->getKey())->get()
            ->map(fn (BranchOpeningHour $h) => ['weekday' => $h->weekday, 'opens_at' => $h->opens_at, 'closes_at' => $h->closes_at]);
        // No schedule configured = always open (same rule as the order pricer).
        $this->hours = $intervals->isEmpty() ? null : new OpeningHoursEvaluator($intervals, $timezone);
    }

    public static function for(Branch $branch, string $timezone, ?CarbonImmutable $now = null): self
    {
        return new self(
            $branch,
            $timezone,
            ($now ?? CarbonImmutable::now())->setTimezone($timezone),
            (int) TenantSettings::get('preorder.lead_minutes'),
            (int) TenantSettings::get('preorder.max_days'),
            max(5, (int) TenantSettings::get('preorder.slot_minutes')),
            (int) TenantSettings::get('preorder.slot_capacity'),
        );
    }

    public function isOpenNow(): bool
    {
        return $this->hours === null || $this->hours->isOpenAt($this->now);
    }

    public function earliest(): CarbonImmutable
    {
        return $this->ceilToSlot($this->now->addMinutes($this->leadMinutes));
    }

    public function latest(): CarbonImmutable
    {
        return $this->now->startOfDay()->addDays($this->maxDays)->endOfDay();
    }

    /**
     * @return list<array{date: string, label: string, slots: list<array{start: string, label: string, available: bool}>}>
     */
    public function days(): array
    {
        $from = $this->earliest();
        $to = $this->latest();
        $taken = $this->taken($from, $to);
        $days = [];

        for ($t = $from; $t->lessThanOrEqualTo($to); $t = $t->addMinutes($this->slotMinutes)) {
            if ($this->hours !== null && ! $this->hours->isOpenAt($t)) {
                continue;
            }

            $date = $t->toDateString();
            $days[$date] ??= ['date' => $date, 'label' => $this->dayLabel($t), 'slots' => []];
            $days[$date]['slots'][] = [
                'start' => $t->utc()->toIso8601String(),
                'label' => JalaliDate::format($t, 'HH:mm', $this->timezone),
                'available' => $this->capacity === 0 || ($taken[$t->getTimestamp()] ?? 0) < $this->capacity,
            ];
        }

        return array_values($days);
    }

    /**
     * Checks a chosen time; at checkout ($forCheckout) the slot count is read under a lock on the
     * branch row, so two customers can't both take the last place.
     */
    public function validate(CarbonImmutable $scheduled, bool $forCheckout): void
    {
        $local = $scheduled->setTimezone($this->timezone);

        if ($local->lessThan($this->now->addMinutes($this->leadMinutes)->subMinute())) {
            throw CommerceException::scheduleTooSoon(JalaliDate::format($this->earliest(), 'HH:mm', $this->timezone));
        }

        if ($local->greaterThan($this->latest())) {
            throw CommerceException::scheduleTooFar();
        }

        if ($this->hours !== null && ! $this->hours->isOpenAt($local)) {
            throw CommerceException::scheduleInvalid();
        }

        if ($this->capacity > 0) {
            if ($forCheckout) {
                Branch::query()->whereKey($this->branch->getKey())->lockForUpdate()->first();
            }
            $slot = $this->floorToSlot($local);
            if (($this->taken($slot, $slot->addMinutes($this->slotMinutes)->subSecond())[$slot->getTimestamp()] ?? 0) >= $this->capacity) {
                throw CommerceException::slotFull();
            }
        }
    }

    /** @return array<int, int> slot start timestamp => live orders in it */
    private function taken(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($this->capacity === 0) {
            return [];
        }

        $counts = [];
        Order::query()->where('branch_id', $this->branch->getKey())
            ->whereBetween('scheduled_for', [$from->utc(), $to->utc()])
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected])
            ->pluck('scheduled_for')
            ->each(function ($at) use (&$counts): void {
                $key = $this->floorToSlot(CarbonImmutable::parse($at)->setTimezone($this->timezone))->getTimestamp();
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            });

        return $counts;
    }

    private function floorToSlot(CarbonImmutable $t): CarbonImmutable
    {
        $minutes = $t->hour * 60 + $t->minute;

        return $t->startOfDay()->addMinutes(intdiv($minutes, $this->slotMinutes) * $this->slotMinutes);
    }

    private function ceilToSlot(CarbonImmutable $t): CarbonImmutable
    {
        $floor = $this->floorToSlot($t);

        return $floor->equalTo($t->startOfMinute()) && $t->second === 0 ? $floor : $floor->addMinutes($this->slotMinutes);
    }

    private function dayLabel(CarbonImmutable $t): string
    {
        $diff = (int) $this->now->startOfDay()->diffInDays($t->startOfDay());

        return match ($diff) {
            0 => 'امروز',
            1 => 'فردا',
            default => JalaliDate::format($t, 'EEEE d MMMM', $this->timezone),
        };
    }
}
