<?php

namespace App\Modules\Customers\Http\Requests;

use App\Support\Localization\PhoneNormalizer;
use App\Support\Validation\IranianMobile;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;

/** Iranian address shape: province, city, district, address, plaque, floor, unit, postal code, map point. */
final class CustomerAddressRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['recipient_phone', 'postal_code', 'building_number', 'floor', 'unit']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:40'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'recipient_phone' => ['nullable', 'string', new IranianMobile],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['required', 'string', 'max:60'],
            'district' => ['nullable', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'digits:10'],
            'building_number' => ['nullable', 'string', 'max:20'],
            'floor' => ['nullable', 'string', 'max:10'],
            'unit' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'notes' => ['nullable', 'string', 'max:300'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function attributesForModel(): array
    {
        $v = $this->validated();
        $v['recipient_phone_e164'] = PhoneNormalizer::tryNormalize($v['recipient_phone'] ?? null);
        unset($v['recipient_phone']);

        return $v;
    }
}
