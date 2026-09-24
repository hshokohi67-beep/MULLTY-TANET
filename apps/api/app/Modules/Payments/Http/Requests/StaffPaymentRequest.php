<?php

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Enums\PaymentMethod;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StaffPaymentRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['amount', 'reference']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::manual()))],
            'amount' => ['nullable', 'integer', 'min:1', 'max:100000000000'], // rial; empty = the remaining balance
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:300'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ];
    }
}
