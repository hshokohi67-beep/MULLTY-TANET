<?php

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Enums\RefundMethod;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RefundRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'], // rial
            'method' => ['required', Rule::enum(RefundMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:300'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ];
    }
}
