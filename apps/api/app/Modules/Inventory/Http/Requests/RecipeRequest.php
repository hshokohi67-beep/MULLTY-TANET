<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class RecipeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $item = fn (string $prefix, bool $negative) => [
            "{$prefix}.*.items" => ['present', 'array', 'max:40'],
            "{$prefix}.*.items.*.ingredient_id" => ['required', 'string', TenantExists::in('ingredients')],
            "{$prefix}.*.items.*.quantity" => ['required', 'numeric', $negative ? 'between:-100000,100000' : 'between:0,100000'],
            "{$prefix}.*.items.*.unit" => ['nullable', 'string', 'in:g,kg,ml,l,pcs,pack'],
        ];

        return [
            'variants' => ['sometimes', 'array', 'max:20'],
            'variants.*.variant_id' => ['required', 'string', TenantExists::in('product_variants')],
            ...$item('variants', false),
            'modifiers' => ['sometimes', 'array', 'max:60'],
            'modifiers.*.modifier_id' => ['required', 'string', TenantExists::in('modifiers')],
            ...$item('modifiers', true),
        ];
    }
}
