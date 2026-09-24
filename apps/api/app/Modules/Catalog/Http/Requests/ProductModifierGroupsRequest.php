<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class ProductModifierGroupsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'modifier_group_ids' => ['present', 'array', 'max:10'],
            'modifier_group_ids.*' => ['string', 'distinct', TenantExists::in('modifier_groups')],
        ];
    }
}
