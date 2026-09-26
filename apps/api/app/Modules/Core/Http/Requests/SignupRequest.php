<?php

namespace App\Modules\Core\Http\Requests;

use App\Support\Localization\PhoneNormalizer;
use App\Support\Validation\IranianMobile;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * A new café's signup. "website" is a honeypot: people never see it, bots fill it. The code step
 * (routes *.signup.otp) only needs the phone.
 */
final class SignupRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['phone', 'code']);
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'phone' => ['required', 'string', 'max:20', new IranianMobile],
            'website' => ['prohibited'],
        ];

        if (! $this->routeIs('*.signup.otp')) {
            $rules += [
                'cafe_name' => ['required', 'string', 'min:2', 'max:80'],
                'slug' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/'],
                'owner_name' => ['required', 'string', 'min:2', 'max:80'],
                'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
                'code' => ['required', 'string', 'regex:/^\d{4,8}$/'],
            ];
        }

        return $rules;
    }

    public function phoneE164(): string
    {
        return PhoneNormalizer::normalize((string) $this->validated('phone'));
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['cafe_name' => 'نام کافه', 'slug' => 'آدرس', 'owner_name' => 'نام شما', 'phone' => 'موبایل', 'password' => 'رمز عبور', 'code' => 'کد تأیید'];
    }
}
