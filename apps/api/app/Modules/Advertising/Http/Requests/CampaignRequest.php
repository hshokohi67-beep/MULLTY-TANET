<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Advertising\Models\AdCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CampaignRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'placement' => ['required', 'string', 'max:24'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'days' => ['required', 'integer', 'between:1,'.AdCampaign::MAX_DAYS],
            'cities' => ['nullable', 'array', 'max:10'],
            'cities.*' => ['string', 'max:60'],
            'headline' => ['required', 'string', 'max:40'],
            'body' => ['nullable', 'string', 'max:90'],
            'cta' => ['required', Rule::in(array_keys(AdCampaign::CTAS))],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'نام کمپین', 'placement' => 'جایگاه', 'start_date' => 'روز شروع', 'days' => 'تعداد روز', 'cities' => 'شهرها', 'headline' => 'تیتر', 'body' => 'متن', 'cta' => 'دکمه'];
    }

    /** @return array{name: string, placement: string, start_date: string, days: int, cities?: list<string>, headline: string, body?: ?string, cta: string} */
    public function campaign(): array
    {
        $v = $this->validated();

        return [
            'name' => (string) $v['name'], 'placement' => (string) $v['placement'], 'start_date' => (string) $v['start_date'], 'days' => (int) $v['days'],
            'cities' => array_values(array_map('strval', $v['cities'] ?? [])), 'headline' => (string) $v['headline'],
            'body' => isset($v['body']) ? (string) $v['body'] : null, 'cta' => (string) $v['cta'],
        ];
    }
}
