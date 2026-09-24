<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class OpeningHoursRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'intervals' => ['present', 'array', 'max:21'],
            'intervals.*.weekday' => ['required', 'integer', 'between:1,7'],
            'intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $perDay = [];

                foreach ((array) $this->input('intervals', []) as $interval) {
                    $day = (int) ($interval['weekday'] ?? 0);
                    $perDay[$day] = ($perDay[$day] ?? 0) + 1;
                }

                if (max([0, ...array_values($perDay)]) > 3) {
                    $validator->errors()->add('intervals', __('validation.max_intervals_per_day'));
                }
            },
        ];
    }

    /** @return list<array{weekday: int, opens_at: string, closes_at: string}> */
    public function intervals(): array
    {
        return array_map(fn (array $i) => [
            'weekday' => (int) $i['weekday'],
            'opens_at' => $i['opens_at'],
            'closes_at' => $i['closes_at'],
        ], array_values($this->validated('intervals')));
    }
}
