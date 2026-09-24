<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Support\Localization\PhoneNormalizer;
use App\Support\Validation\IranianMobile;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['contact_phone']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'address_id' => ['nullable', 'string', 'max:26'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:300'],
            'scheduled_for' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', new IranianMobile],
            // cash = pay at the counter / on delivery; online = gateway right after checkout.
            'payment_method' => ['sometimes', Rule::in(['cash', 'online'])],
        ];
    }

    public function contactPhoneE164(): ?string
    {
        return PhoneNormalizer::tryNormalize($this->validated('contact_phone'));
    }
}
