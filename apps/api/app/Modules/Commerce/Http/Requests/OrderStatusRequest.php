<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OrderStatusRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)->except([OrderStatus::PendingPayment, OrderStatus::Placed])],
            'note' => ['nullable', 'string', 'max:300', Rule::requiredIf(in_array($this->input('status'), ['cancelled', 'rejected'], true))],
        ];
    }
}
