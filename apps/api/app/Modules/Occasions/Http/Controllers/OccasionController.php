<?php

namespace App\Modules\Occasions\Http\Controllers;

use App\Modules\Occasions\Enums\OccasionStatus;
use App\Modules\Occasions\Models\OccasionDate;
use App\Modules\Occasions\Models\TenantOccasion;
use App\Modules\Occasions\Support\UpcomingOccasions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The café's calendar: what is coming, and its own answer to each occurrence. */
final class OccasionController
{
    public function upcoming(Request $request): JsonResponse
    {
        $v = $request->validate(['days' => ['sometimes', 'integer', 'between:7,400']]);

        return response()->json(['data' => UpcomingOccasions::forCurrentTenant((int) ($v['days'] ?? UpcomingOccasions::WINDOW_DAYS))]);
    }

    public function state(Request $request, OccasionDate $occasionDate): JsonResponse
    {
        $status = $request->validate(['status' => ['present', 'nullable', Rule::enum(OccasionStatus::class)]])['status'];

        $state = TenantOccasion::query()->firstOrCreate(['occasion_date_id' => $occasionDate->id]);
        $state->update(['status' => $status]);

        return response()->json(['data' => ['id' => $occasionDate->id, 'status' => $state->status?->value]]);
    }
}
