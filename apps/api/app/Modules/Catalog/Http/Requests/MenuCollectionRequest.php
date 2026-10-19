<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\MenuCollection;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MenuCollectionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'theme' => ['nullable', Rule::in(MenuCollection::THEMES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'occasion_key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['required', 'array', 'min:1', 'max:20'],
            'product_ids.*' => ['string', 'distinct', TenantExists::in('products', withoutTrashed: true)],
        ];
    }
}
