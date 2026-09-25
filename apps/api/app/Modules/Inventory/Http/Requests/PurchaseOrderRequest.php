<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Support\Validation\NormalizesPersianInput;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class PurchaseOrderRequest extends FormRequest
{
    use NormalizesPersianInput;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'string', TenantExists::in('suppliers')],
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'expected_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.ingredient_id' => ['required', 'string', 'distinct', TenantExists::in('ingredients')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'items.*.unit' => ['nullable', 'string', 'in:g,kg,ml,l,pcs,pack'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:100000000000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['supplier_id' => 'تأمین‌کننده', 'items' => 'اقلام', 'items.*.quantity' => 'مقدار', 'items.*.unit_price' => 'قیمت'];
    }
}
