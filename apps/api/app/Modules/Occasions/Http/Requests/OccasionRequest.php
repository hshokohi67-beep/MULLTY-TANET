<?php

namespace App\Modules\Occasions\Http\Requests;

use App\Modules\Occasions\Enums\OccasionCalendar;
use App\Modules\Occasions\Enums\OccasionKind;
use App\Modules\Occasions\Enums\OccasionTone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An occasion in the platform calendar; a one-off one carries its Gregorian `date`. */
final class OccasionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // One-off occasions carry a date instead; rule-based ones (Chaharshanbe Suri…) have no day when edited.
        $oneOff = $this->boolean('is_one_off') || $this->isMethod('PUT');

        return [
            'key' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('occasions', 'key')->ignore($this->route('occasion'))],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'kind' => ['required', Rule::enum(OccasionKind::class)],
            'tone' => ['required', Rule::enum(OccasionTone::class)],
            'calendar' => ['required', Rule::enum(OccasionCalendar::class)],
            'month' => [$oneOff ? 'nullable' : 'required', 'integer', 'between:1,12'],
            'day' => [$oneOff ? 'nullable' : 'required', 'integer', 'between:1,31'],
            'duration_days' => ['required', 'integer', 'between:1,40'],
            'lead_days' => ['required', 'integer', 'between:0,60'],
            'is_holiday' => ['sometimes', 'boolean'],
            'ideas' => ['nullable', 'array', 'max:8'],
            'ideas.*' => ['string', 'max:160'],
            'campaign_text' => ['nullable', 'string', 'max:300'],
            'theme' => ['nullable', Rule::in(['nowruz', 'yalda', 'autumn', 'winter', 'summer', 'ramadan'])],
            'is_active' => ['sometimes', 'boolean'],
            'is_one_off' => ['sometimes', 'boolean'],
            'date' => [$this->boolean('is_one_off') && $this->isMethod('POST') ? 'required' : 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
