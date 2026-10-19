<?php

namespace App\Modules\Occasions\Support;

use App\Modules\Core\Support\TenantSettings;
use App\Modules\Occasions\Enums\OccasionKind;
use App\Modules\Occasions\Enums\OccasionTone;
use App\Modules\Occasions\Models\OccasionDate;
use App\Modules\Occasions\Models\TenantOccasion;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * The current café's view of the calendar: occurrences that are on now or start within the
 * window, limited to the kinds it follows, each with the café's own answer. Days are counted in
 * the café's timezone.
 */
final class UpcomingOccasions
{
    public const WINDOW_DAYS = 45;

    /** @return list<OccasionKind> */
    public static function followedKinds(): array
    {
        $kinds = array_filter(explode(',', (string) TenantSettings::get('occasions.kinds')));

        return array_values(array_filter(array_map(fn (string $k) => OccasionKind::tryFrom($k), $kinds)));
    }

    /** @return list<array<string, mixed>> */
    public static function forCurrentTenant(int $days = self::WINDOW_DAYS, ?CarbonImmutable $now = null): array
    {
        $tenant = app(TenantContext::class)->require();
        $today = ($now ?? CarbonImmutable::now($tenant->timezone))->setTimezone($tenant->timezone)->startOfDay();
        $kinds = array_map(fn (OccasionKind $k) => $k->value, self::followedKinds());
        if ($kinds === []) {
            return [];
        }

        $dates = OccasionDate::query()
            ->with('occasion')
            ->whereHas('occasion', fn ($q) => $q->where('is_active', true)->whereIn('kind', $kinds))
            ->where('ends_on', '>=', $today->toDateString())
            ->where('starts_on', '<=', $today->addDays($days)->toDateString())
            ->orderBy('starts_on')
            ->get();

        $states = TenantOccasion::query()->whereIn('occasion_date_id', $dates->modelKeys())->get()->keyBy('occasion_date_id');

        return $dates->map(function (OccasionDate $d) use ($today, $states): array {
            $o = $d->occasion;
            $start = CarbonImmutable::parse($d->starts_on->toDateString(), $today->getTimezone());
            $daysLeft = (int) $today->diffInDays($start, false);
            $state = $states->get($d->id);

            return [
                'id' => $d->id,
                'key' => $o->key,
                'title' => $o->title,
                'description' => $o->description,
                'kind' => $o->kind->value,
                'kind_label' => $o->kind->label(),
                'tone' => $o->tone->value,
                'starts_on' => $d->starts_on->toDateString(),
                'ends_on' => $d->ends_on->toDateString(),
                'days_left' => max(0, $daysLeft),
                'is_ongoing' => $daysLeft <= 0,
                'approximate' => $d->isApproximate(),
                'is_holiday' => $o->is_holiday,
                'lead_days' => $o->lead_days,
                'in_lead' => $daysLeft <= $o->lead_days,
                'ideas' => $o->ideas ?? [],
                'campaign_text' => $o->tone === OccasionTone::Mourning ? null : $o->campaign_text,
                'theme' => $o->tone === OccasionTone::Mourning ? null : $o->theme,
                'status' => $state?->status?->value,
            ];
        })->values()->all();
    }
}
