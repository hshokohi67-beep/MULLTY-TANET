<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Models\CartItem;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class CartItemRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $adding = $this->isMethod('POST');

        return [
            'variant_id' => [$adding ? 'required' : 'prohibited', 'string', TenantExists::in('product_variants')],
            'quantity' => ['required', 'integer', 'between:'.($adding ? 1 : 0).','.CartItem::MAX_QUANTITY],
            'modifier_ids' => ['sometimes', 'array', 'max:30'],
            'modifier_ids.*' => ['string', 'distinct', TenantExists::in('modifiers')],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
