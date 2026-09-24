<?php

namespace App\Modules\Loyalty\Http\Requests;

use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;

/** A manual wallet (rial) or points delta. Never an absolute balance, always with a reason. */
final class AdjustmentRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['amount']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'not_in:0', 'between:-100000000000,100000000000'],
            'reason' => ['required', 'string', 'max:300'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ];
    }
}
