<?php

namespace App\Modules\Occasions\Http\Controllers;

use App\Modules\Occasions\Actions\ResolveOccasionDates;
use App\Modules\Occasions\Http\Requests\OccasionRequest;
use App\Modules\Occasions\Models\Occasion;
use App\Modules\Occasions\Models\OccasionDate;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The platform admin's calendar: edit occasions, add one-off dates, and confirm or move each
 * year's Hijri dates (moon sighting).
 */
final class PlatformOccasionController
{
    public function index(): JsonResponse
    {
        $from = CarbonImmutable::now('Asia/Tehran')->subDays(7)->toDateString();
        $occasions = Occasion::query()->with(['dates' => fn ($q) => $q->where('ends_on', '>=', $from)->orderBy('starts_on')])->get()
            ->sortBy(fn (Occasion $o) => $o->dates->first()?->starts_on?->toDateString() ?? '9999')
            ->values();

        return response()->json(['data' => $occasions->map(fn (Occasion $o) => $this->present($o))->all()]);
    }

    public function store(OccasionRequest $request, ResolveOccasionDates $resolve, AuditLogger $audit): JsonResponse
    {
        $data = $request->validated();
        $date = $data['date'] ?? null;
        unset($data['date']);
        $occasion = Occasion::query()->create([...$data, 'key' => $data['key'] ?? 'custom_'.strtolower((string) str()->ulid())]);

        if ($occasion->is_one_off && $date !== null) {
            $start = CarbonImmutable::parse($date);
            OccasionDate::query()->create(['occasion_id' => $occasion->id, 'year' => (int) $start->format('Y'), 'starts_on' => $start->toDateString(),
                'ends_on' => $start->addDays($occasion->duration_days - 1)->toDateString(), 'confirmed' => true]);
        } else {
            $resolve->handle();
        }
        $audit->record('occasion.created', $occasion, ['title' => $occasion->title]);

        return response()->json(['data' => $this->present($occasion->load('dates'))], 201);
    }

    public function update(OccasionRequest $request, Occasion $occasion, ResolveOccasionDates $resolve, AuditLogger $audit): JsonResponse
    {
        $data = $request->validated();
        unset($data['date'], $data['key']);
        $occasion->update($data);
        $resolve->handle();
        $audit->record('occasion.updated', $occasion, $occasion->getChanges());

        return response()->json(['data' => $this->present($occasion->load('dates'))]);
    }

    /** Confirm a resolved date, optionally moving it (e.g. one day later after moon sighting). */
    public function confirmDate(Request $request, OccasionDate $occasionDate, AuditLogger $audit): JsonResponse
    {
        $v = $request->validate(['starts_on' => ['required', 'date_format:Y-m-d']]);
        $start = CarbonImmutable::parse($v['starts_on']);
        $occasionDate->update([
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->addDays(max(1, $occasionDate->occasion->duration_days) - 1)->toDateString(),
            'confirmed' => true,
        ]);
        $audit->record('occasion.date_confirmed', $occasionDate, ['starts_on' => $start->toDateString()]);

        return response()->json(['data' => $this->present($occasionDate->occasion->load('dates'))]);
    }

    /** @return array<string, mixed> */
    private function present(Occasion $o): array
    {
        return [
            ...$o->only(['id', 'key', 'title', 'description', 'month', 'day', 'rule', 'duration_days', 'lead_days', 'is_holiday', 'campaign_text', 'theme', 'is_active', 'is_one_off']),
            'kind' => $o->kind->value,
            'kind_label' => $o->kind->label(),
            'tone' => $o->tone->value,
            'calendar' => $o->calendar->value,
            'ideas' => $o->ideas ?? [],
            'dates' => $o->dates->map(fn (OccasionDate $d) => [
                'id' => $d->id, 'year' => $d->year, 'starts_on' => $d->starts_on->toDateString(), 'ends_on' => $d->ends_on->toDateString(),
                'confirmed' => $d->confirmed, 'approximate' => $d->isApproximate(),
            ])->values()->all(),
        ];
    }
}
