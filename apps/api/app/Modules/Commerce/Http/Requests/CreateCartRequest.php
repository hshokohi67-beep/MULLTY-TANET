<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Enums\OrderType;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCartRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order_type' => ['required', Rule::in(array_map(fn (OrderType $t) => $t->value, OrderType::customerFacing()))],
            // QR table carts take their branch from the table session.
            'branch_id' => ['required_unless:order_type,qr_table', 'nullable', 'string', TenantExists::in('branches')],
        ];
    }
}
