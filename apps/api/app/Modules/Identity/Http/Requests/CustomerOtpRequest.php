<?php

namespace App\Modules\Identity\Http\Requests;

use App\Support\Localization\PhoneNormalizer;
use App\Support\Validation\IranianMobile;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;

final class CustomerOtpRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['phone', 'code']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['phone' => ['required', 'string', 'max:20', new IranianMobile]];

        if ($this->routeIs('*.otp.verify')) {
            $rules['code'] = ['required', 'string', 'regex:/^\d{4,8}$/'];
            $rules['device_name'] = ['nullable', 'string', 'max:60'];
        }

        return $rules;
    }

    public function phoneE164(): string
    {
        return PhoneNormalizer::normalize((string) $this->validated('phone'));
    }
}
