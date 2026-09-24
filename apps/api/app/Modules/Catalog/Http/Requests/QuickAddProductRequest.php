<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

/** "Quick Add": the minimum to put an item on the menu. Everything else can be filled in later. */
final class QuickAddProductRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'price' => ['required', 'integer', 'min:0', 'max:'.ProductRequest::MAX_PRICE],
            'category_id' => ['nullable', 'string', TenantExists::in('categories')],
        ];
    }
}
