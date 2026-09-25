<?php

namespace App\Modules\Marketplace\Http\Requests;

use App\Modules\Marketplace\Support\MarketplaceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_listed' => ['required', 'boolean'],
            'headline' => ['nullable', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:600'],
            'categories' => ['present', 'array', 'max:'.MarketplaceCatalog::MAX_CATEGORIES],
            'categories.*' => ['distinct', Rule::in(array_keys(MarketplaceCatalog::CATEGORIES))],
            'amenities' => ['present', 'array', 'max:'.count(MarketplaceCatalog::AMENITIES)],
            'amenities.*' => ['distinct', Rule::in(array_keys(MarketplaceCatalog::AMENITIES))],
            'price_level' => ['nullable', 'integer', 'between:1,4'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['headline' => 'جمله‌ی معرفی', 'about' => 'درباره‌ی ما', 'categories' => 'دسته‌ها', 'amenities' => 'امکانات', 'price_level' => 'سطح قیمت'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['categories.max' => 'حداکثر '.MarketplaceCatalog::MAX_CATEGORIES.' دسته انتخاب کنید.'];
    }
}
