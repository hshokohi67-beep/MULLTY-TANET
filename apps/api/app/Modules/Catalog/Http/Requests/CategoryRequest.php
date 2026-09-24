<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CategoryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'string', TenantExists::in('categories')],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
            'is_active' => ['sometimes', 'boolean'],
            // Menu mood: hot drinks/food glow warm, cold ones cool. Null = neutral.
            'temperature' => ['sometimes', 'nullable', Rule::in(['hot', 'cold'])],
        ];
    }
}
